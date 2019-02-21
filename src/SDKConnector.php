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
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
class SDKConnector implements SDKConnectorInterface {

  /**
   * The client object.
   *
   * @var null|\Http\Client\HttpClient
   */
  private static $client = NULL;

  /**
   * The currently used credentials object.
   *
   * @var null|\Drupal\apigee_edge\CredentialsInterface
   */
  private static $credentials = NULL;

  /**
   * Custom user agent prefix.
   *
   * @var null|string
   */
  private static $userAgentPrefix = NULL;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The info parser.
   *
   * @var \Drupal\Core\Extension\InfoParserInterface
   */
  protected $infoParser;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param \Drupal\Core\Extension\InfoParserInterface $info_parser
   *   Info file parser service.
   */
  public function __construct(ClientFactory $client_factory, KeyRepositoryInterface $key_repository, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, InfoParserInterface $info_parser) {
    $this->clientFactory = $client_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->keyRepository = $key_repository;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->infoParser = $info_parser;
  }

  /**
   * Get HTTP client overrides for Apigee Edge API client.
   *
   * Allows to override some configuration of the http client built by the
   * factory for the API client.
   *
   * @return array
   *   Associative array of configuration settings.
   *
   * @see http://docs.guzzlephp.org/en/stable/request-options.html
   */
  protected function httpClientConfiguration(): array {
    return [
      'connect_timeout' => $this->configFactory->get('apigee_edge.client')->get('http_client_connect_timeout') ?? 30,
      'timeout' => $this->configFactory->get('apigee_edge.client')->get('http_client_timeout') ?? 30,
      'proxy' => $this->configFactory->get('apigee_edge.client')->get('http_client_proxy') ?? '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(): string {
    $credentials = $this->getCredentials();
    return $credentials->getKeyType()->getOrganization($credentials->getKey());
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(?Authentication $authentication = NULL, ?string $endpoint = NULL): ClientInterface {
    if ($authentication === NULL) {
      if (self::$client === NULL) {
        $credentials = $this->getCredentials();
        /** @var \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface $key_type */
        self::$client = $this->buildClient($credentials->getAuthentication(), $credentials->getKeyType()->getEndpoint($credentials->getKey()));
      }

      return self::$client;
    }
    else {
      return $this->buildClient($authentication, $endpoint);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildClient(Authentication $authentication, ?string $endpoint = NULL, array $options = []): ClientInterface {
    $options += [
      Client::CONFIG_HTTP_CLIENT_BUILDER => new Builder(new GuzzleClientAdapter($this->clientFactory->fromOptions($this->httpClientConfiguration()))),
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
    if (self::$credentials === NULL) {
      $active_key = $this->configFactory->get('apigee_edge.auth')->get('active_key');
      if (empty($active_key)) {
        throw new AuthenticationKeyException('Apigee Edge API authentication key is not set.');
      }
      if (!($key = $this->keyRepository->getKey($active_key))) {
        throw new AuthenticationKeyNotFoundException($active_key, 'Apigee Edge API authentication key not found with "@id" id.');
      }
      self::$credentials = $this->buildCredentials($key);
    }

    return self::$credentials;
  }

  /**
   * Changes credentials used by the API client.
   *
   * @param \Drupal\apigee_edge\CredentialsInterface $credentials
   *   The new credentials object.
   */
  private function setCredentials(CredentialsInterface $credentials) {
    self::$credentials = $credentials;
    // Ensure that client will be rebuilt with the new key.
    self::$client = NULL;
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
    /** @var \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface $key */
    if ($key->getKeyType() instanceof EdgeKeyTypeInterface) {
      if ($key->getKeyType()->getAuthenticationType($key) === EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH) {
        return new OauthCredentials($key);
      }
      return new Credentials($key);
    }
    else {
      throw new AuthenticationKeyException("Type of {$key->id()} key does not implement EdgeKeyTypeInterface.");
    }
  }

  /**
   * Generates a custom user agent prefix.
   */
  protected function userAgentPrefix(): string {
    if (NULL === self::$userAgentPrefix) {
      $module_info = $this->infoParser->parse($this->moduleHandler->getModule('apigee_edge')->getPathname());
      if (!isset($module_info['version'])) {
        $module_info['version'] = '8.x-1.x-dev';
      }
      // TODO Change "DevPortal" to "Drupal module" later. It has been added for
      // Apigee's convenience this way.
      self::$userAgentPrefix = $module_info['name'] . ' DevPortal ' . $module_info['version'];
    }

    return self::$userAgentPrefix;
  }

  /**
   * {@inheritdoc}
   */
  public function testConnection(KeyInterface $key = NULL) {
    if ($key !== NULL) {
      $credentials = $this->buildCredentials($key);
      $client = $this->buildClient($credentials->getAuthentication(), $credentials->getKeyType()->getEndpoint($credentials->getKey()));
    }
    else {
      $client = $this->getClient();
      $credentials = $this->getCredentials();
    }

    try {
      // We use the original, non-decorated organization controller here.
      $oc = new OrganizationController($client);
      $oc->load($credentials->getKeyType()->getOrganization($credentials->getKey()));
    }
    catch (\Exception $e) {
      throw $e;
    }
    finally {
      if (isset($original_credentials)) {
        self::$credentials = $this->setCredentials($original_credentials);
      }
    }
  }

}
