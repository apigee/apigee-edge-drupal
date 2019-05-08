<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge;

use Apigee\Edge\Api\Management\Controller\OrganizationController;
use Apigee\Edge\Client;
use Apigee\Edge\ClientInterface;
use Apigee\Edge\HttpClient\Utility\Builder;
use Drupal\apigee_edge\Exception\AuthenticationKeyException;
use Drupal\apigee_edge\Exception\AuthenticationKeyNotFoundException;
use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Http\Adapter\Guzzle6\Client as GuzzleClientAdapter;
use Http\Message\Authentication;

/**
 * Provides an Apigee Edge SDK connector.
 */
final class SDKConnector implements SDKConnectorInterface {

  /**
   * HTTP client configuration options for ClientFactory.
   *
   * @param string
   */
  public const CLIENT_FACTORY_OPTIONS = 'client_factory_options';

  /**
   * The API client initialized from the saved credentials and default config.
   *
   * @var null|\Apigee\Edge\ClientInterface
   *
   * @see getClient()
   */
  private $defaultClient;

  /**
   * The currently used credentials object.
   *
   * @var null|\Drupal\apigee_edge\CredentialsInterface
   */
  private $credentials;

  /**
   * Custom user agent prefix.
   *
   * @var null|string
   *
   * @see userAgentPrefix()
   */
  private $userAgentPrefix;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * The key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  private $keyRepository;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The info parser.
   *
   * @var \Drupal\Core\Extension\InfoParserInterface
   */
  private $infoParser;

  /**
   * The HTTP client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  private $clientFactory;

  /**
   * Constructs a new SDKConnector.
   *
   * @param \Drupal\Core\Http\ClientFactory $client_factory
   *   Http client.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param \Drupal\Core\Extension\InfoParserInterface $info_parser
   *   Info file parser service.
   */
  public function __construct(ClientFactory $client_factory, KeyRepositoryInterface $key_repository, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, InfoParserInterface $info_parser) {
    $this->clientFactory = $client_factory;
    $this->keyRepository = $key_repository;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->infoParser = $info_parser;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(): string {
    try {
      $credentials = $this->getCredentials();
    }
    catch (AuthenticationKeyException $e) {
      return '';
    }
    return $credentials->getKeyType()->getOrganization($credentials->getKey());
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(?Authentication $authentication = NULL, ?string $endpoint = NULL, array $options = []): ClientInterface {
    // If method got called without default parameters, initialize and/or
    // return the default API client from the internal cache.
    if (!isset($authentication, $endpoint) && empty($options)) {
      if ($this->defaultClient === NULL) {
        $credentials = $this->getCredentials();
        $this->defaultClient = $this->buildClient($credentials->getAuthentication(), $credentials->getKeyType()->getEndpoint($credentials->getKey()));
      }

      return $this->defaultClient;
    }

    // Fallback to the saved credentials.
    if ($authentication === NULL) {
      $credentials = $this->getCredentials();
      $authentication = $credentials->getAuthentication();
    }

    return $this->buildClient($authentication, $endpoint, $options);
  }

  /**
   * {@inheritdoc}
   */
  private function buildClient(Authentication $authentication, ?string $endpoint = NULL, array $options = []): ClientInterface {
    $default_client_options = [
      'connect_timeout' => $this->configFactory->get('apigee_edge.client')->get('http_client_connect_timeout') ?? 30,
      'timeout' => $this->configFactory->get('apigee_edge.client')->get('http_client_timeout') ?? 30,
      'proxy' => $this->configFactory->get('apigee_edge.client')->get('http_client_proxy') ?? '',
    ];
    if (isset($options[static::CLIENT_FACTORY_OPTIONS])) {
      $http_client_options = NestedArray::mergeDeep($default_client_options, $options[static::CLIENT_FACTORY_OPTIONS]);
      unset($options[static::CLIENT_FACTORY_OPTIONS]);
    }
    else {
      $http_client_options = $default_client_options;
    }

    // Builder and user-agent prefix can not be overridden.
    $options += [
      Client::CONFIG_HTTP_CLIENT_BUILDER => new Builder(new GuzzleClientAdapter($this->clientFactory->fromOptions($http_client_options))),
      Client::CONFIG_USER_AGENT_PREFIX => $this->userAgentPrefix(),
    ];
    return new Client($authentication, $endpoint, $options);
  }

  /**
   * Returns the credentials object used by the API client.
   *
   * @return \Drupal\apigee_edge\CredentialsInterface
   *   The key entity.
   */
  private function getCredentials(): CredentialsInterface {
    if ($this->credentials === NULL) {
      $active_key = $this->configFactory->get('apigee_edge.auth')->get('active_key');
      if (empty($active_key)) {
        throw new AuthenticationKeyException('Apigee Edge API authentication key is not set.');
      }
      $key = $this->keyRepository->getKey($active_key);
      if (!$key) {
        throw new AuthenticationKeyNotFoundException($active_key, 'Apigee Edge API authentication key not found with "@id" id.');
      }
      $this->credentials = $this->buildCredentials($key);
    }

    return $this->credentials;
  }

  /**
   * Builds credentials, which depends on the KeyType of the key entity.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity which stores the API credentials.
   *
   * @return \Drupal\apigee_edge\CredentialsInterface
   *   The credentials.
   */
  private function buildCredentials(KeyInterface $key): CredentialsInterface {
    if ($key->getKeyType() instanceof EdgeKeyTypeInterface) {
      if ($key->getKeyType()->getAuthenticationType($key) === EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH) {
        return new OauthCredentials($key);
      }
      return new Credentials($key);
    }

    throw new AuthenticationKeyException("Type of {$key->id()} key does not implement EdgeKeyTypeInterface.");
  }

  /**
   * Generates a custom user agent prefix.
   */
  private function userAgentPrefix(): string {
    if ($this->userAgentPrefix === NULL) {
      $module_info = $this->infoParser->parse($this->moduleHandler->getModule('apigee_edge')->getPathname());
      if (!isset($module_info['version'])) {
        $module_info['version'] = '8.x-1.x-dev';
      }
      // TODO Change "DevPortal" to "Drupal module" later. It has been added for
      // Apigee's convenience this way.
      $this->userAgentPrefix = $module_info['name'] . ' DevPortal ' . $module_info['version'];
    }

    return $this->userAgentPrefix;
  }

  /**
   * {@inheritdoc}
   */
  public function testConnection(KeyInterface $key = NULL): void {
    if ($key === NULL) {
      $client = $this->getClient();
      $credentials = $this->getCredentials();
    }
    else {
      $credentials = $this->buildCredentials($key);
      $client = $this->buildClient($credentials->getAuthentication(), $credentials->getKeyType()->getEndpoint($credentials->getKey()));
    }

    try {
      // We use the original, non-decorated organization controller here.
      $oc = new OrganizationController($client);
      $oc->load($credentials->getKeyType()->getOrganization($credentials->getKey()));
    }
    catch (\Exception $e) {
      throw new AuthenticationKeyException($e->getMessage(), $e->getCode(), $e);
    }
  }

}
