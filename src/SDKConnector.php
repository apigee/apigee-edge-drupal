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
use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Apigee\Edge\Exception\UnknownEndpointException;
use Apigee\Edge\HttpClient\Client;
use Apigee\Edge\HttpClient\ClientInterface;
use Apigee\Edge\HttpClient\Utility\Builder;
use Drupal\apigee_edge\Entity\Storage\EdgeEntityStorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Http\Adapter\Guzzle6\Client as GuzzleClientAdapter;

/**
 * Provides an Apigee Edge SDK connector.
 */
class SDKConnector implements SDKConnectorInterface {

  /**
   * @var null|\Http\Client\HttpClient*/
  private static $client = NULL;

  /**
   * @var null|\Drupal\apigee_edge\CredentialsInterface*/
  private static $credentials = NULL;

  /**
   * @var null|string*/
  private static $userAgentPrefix = NULL;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The currently used credentials storage plugin.
   *
   * @var \Drupal\apigee_edge\CredentialsStoragePluginInterface
   */
  protected $credentialsStoragePlugin;

  /**
   * The currently used authentication method plugin.
   *
   * @var \Drupal\apigee_edge\AuthenticationMethodPluginInterface
   */
  protected $authenticationMethodPlugin;

  /**
   * @var \Http\Client\HttpClient
   */
  protected $httpClient;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Extension\InfoParserInterface
   */
  protected $infoParser;

  /**
   * Constructs a new SDKConnector.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   Http client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\apigee_edge\CredentialsStorageManager $credentials_storage_plugin_manager
   *   The manager for credentials storage plugins.
   * @param \Drupal\apigee_edge\AuthenticationMethodManager $authentication_method_plugin_manager
   *   The manager for authentication method plugins.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   * @param \Drupal\Core\Extension\InfoParserInterface $infoParser
   *   Info file parser service.
   */
  public function __construct(GuzzleClientInterface $httpClient, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, CredentialsStorageManager $credentials_storage_plugin_manager, AuthenticationMethodManager $authentication_method_plugin_manager, ModuleHandlerInterface $moduleHandler, InfoParserInterface $infoParser) {
    $this->httpClient = new GuzzleClientAdapter($httpClient);
    $this->entityTypeManager = $entity_type_manager;
    $this->credentialsStoragePlugin = $credentials_storage_plugin_manager->createInstance($config_factory->get('apigee_edge.credentials_storage')->get('credentials_storage_type'));
    $this->authenticationMethodPlugin = $authentication_method_plugin_manager->createInstance($config_factory->get('apigee_edge.authentication_method')->get('authentication_method'));
    $this->moduleHandler = $moduleHandler;
    $this->infoParser = $infoParser;
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
  public function getClient() : ClientInterface {
    if (NULL === self::$client) {
      $builder = new Builder($this->httpClient);
      $credentials = $this->credentialsStoragePlugin->loadCredentials();
      $auth = $this->authenticationMethodPlugin->createAuthenticationObject($credentials);
      self::$client = new Client($auth, $builder, $credentials->getEndpoint(), $this->userAgentPrefix());
    }
    return self::$client;
  }

  /**
   * Returns credentials used by the API client.
   *
   * @return \Drupal\apigee_edge\CredentialsInterface
   *   Credential object.
   */
  private function getCredentials() : CredentialsInterface {
    if (NULL === self::$credentials) {
      self::$credentials = $this->credentialsStoragePlugin->loadCredentials();
    }

    return self::$credentials;
  }

  /**
   * Changes credentials used by the API client.
   *
   * @param \Drupal\apigee_edge\CredentialsInterface $credentials
   *   Credentials object.
   */
  private function setCredentials(CredentialsInterface $credentials) {
    self::$credentials = $credentials;
    // Ensure that client will be rebuilt with the new credentials.
    self::$client = NULL;
  }

  /**
   * Generates a custom user agent prefix.
   */
  protected function userAgentPrefix(): string {
    if (NULL === self::$userAgentPrefix) {
      $moduleInfo = $this->infoParser->parse($this->moduleHandler->getModule('apigee_edge')->getPathname());
      if (!isset($moduleInfo['version'])) {
        $moduleInfo['version'] = '8.x-1.0-dev';
      }
      self::$userAgentPrefix = $moduleInfo['name'] . ' ' . $moduleInfo['version'];
    }

    return self::$userAgentPrefix;
  }

  /**
   * {@inheritdoc}
   */
  public function getControllerByEntity(string $entity_type) :  EntityCrudOperationsControllerInterface {
    $controller = $this->entityTypeManager->getStorage($entity_type);
    if ($controller instanceof EdgeEntityStorageInterface) {
      return $controller->getController($this);
    }

    throw new UnknownEndpointException($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function testConnection(CredentialsInterface $credentials = NULL) {
    if (NULL !== $credentials) {
      $originalCredentials = self::getCredentials();
      self::setCredentials($credentials);
    }
    $oc = new OrganizationController($this->getClient());
    try {
      $oc->load($this->getCredentials()->getOrganization());
    }
    catch (\Exception $e) {
      throw $e;
    }
    finally {
      if (isset($originalCredentials)) {
        self::setCredentials($originalCredentials);
      }
    }
  }

}
