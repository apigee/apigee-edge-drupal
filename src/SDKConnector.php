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
use Apigee\Edge\Exception\UnknownEndpointException;
use Apigee\Edge\Client;
use Apigee\Edge\ClientInterface;
use Apigee\Edge\HttpClient\Utility\Builder;
use Drupal\apigee_edge\Entity\Controller\DrupalEntityControllerInterface;
use Drupal\apigee_edge\Entity\Storage\EdgeEntityStorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Http\Adapter\Guzzle6\Client as GuzzleClientAdapter;

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
   * The HttpClient.
   *
   * @var \Http\Client\HttpClient
   */
  protected $httpClient;

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
   * Constructs a new SDKConnector.
   *
   * @param \Drupal\Core\Http\ClientFactory $clientFactory
   *   Http client.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   * @param \Drupal\Core\Extension\InfoParserInterface $infoParser
   *   Info file parser service.
   */
  public function __construct(ClientFactory $clientFactory, KeyRepositoryInterface $key_repository, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, InfoParserInterface $infoParser) {
    $httpClient = $clientFactory->fromOptions($this->getHttpClientConfiguration($config_factory));
    $this->httpClient = new GuzzleClientAdapter($httpClient);
    $this->entityTypeManager = $entity_type_manager;
    $this->keyRepository = $key_repository;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $moduleHandler;
    $this->infoParser = $infoParser;
  }

  /**
   * Get HTTP client overrides for APIgee API client.
   *
   * Allows to override some configuration of the http client built by the
   * factory for the API client.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory object.
   *
   * @return array
   *   Associative array of configuration settings.
   *
   * @see http://docs.guzzlephp.org/en/stable/request-options.html
   */
  protected function getHttpClientConfiguration(ConfigFactoryInterface $config_factory) : array {
    return [
      'timeout' => $config_factory->get('apigee_edge.client')->get('http_client_timeout'),
      'proxy' => $config_factory->get('apigee_edge.client')->get('http_client_proxy'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(): string {
    return $this->getCredentials()->getOrganization();
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(): ClientInterface {
    if (self::$client === NULL) {
      /** @var \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface $key_type */
      self::$client = new Client($this->getCredentials()->getAuthentication(), $this->getCredentials()->getEndpoint(), [
        Client::CONFIG_HTTP_CLIENT_BUILDER => new Builder($this->httpClient),
        Client::CONFIG_USER_AGENT_PREFIX => $this->userAgentPrefix(),
      ]);
    }
    return self::$client;
  }

  /**
   * Returns the credentials object used by the API client.
   *
   * @return \Drupal\apigee_edge\CredentialsInterface
   *   The key entity.
   */
  private function getCredentials(): CredentialsInterface {
    if (self::$credentials === NULL) {
      $key = $this->keyRepository->getKey($this->configFactory->get('apigee_edge.client')->get('active_key'));
      if ($key === NULL) {
        throw new KeyNotFoundException($this->configFactory->get('apigee_edge.client')->get('active_key'));
      }
      self::$credentials = new Credentials($key);
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
   * Generates a custom user agent prefix.
   */
  protected function userAgentPrefix() : string {
    if (NULL === self::$userAgentPrefix) {
      $moduleInfo = $this->infoParser->parse($this->moduleHandler->getModule('apigee_edge')->getPathname());
      if (!isset($moduleInfo['version'])) {
        $moduleInfo['version'] = '8.x-1.x-dev';
      }
      // TODO Change "DevPortal" to "Drupal module" later. It has been added for
      // Apigee's convenience this way.
      self::$userAgentPrefix = $moduleInfo['name'] . ' DevPortal ' . $moduleInfo['version'];
    }

    return self::$userAgentPrefix;
  }

  /**
   * {@inheritdoc}
   */
  public function getControllerByEntity(string $entity_type) :  DrupalEntityControllerInterface {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    if ($storage instanceof EdgeEntityStorageInterface) {
      return $storage->getController($this);
    }

    throw new UnknownEndpointException($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function testConnection(KeyInterface $key = NULL) {
    if ($key !== NULL) {
      try {
        $originalCredentials = self::getCredentials();
      }
      catch (KeyNotFoundException $e) {
        // Skip key not set exception if there is no currently used key.
      }
      self::setCredentials(new Credentials($key));
    }
    try {
      $oc = new OrganizationController($this->getClient());
      $oc->load($this->getCredentials()->getOrganization());
    }
    catch (\Exception $e) {
      throw $e;
    }
    finally {
      if (isset($originalCredentials)) {
        self::$credentials = $this->setCredentials($originalCredentials);
      }
    }
  }

}
