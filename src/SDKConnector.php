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
use Drupal\apigee_edge\Entity\Storage\EdgeEntityStorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides an Apigee Edge SDK connector.
 */
class SDKConnector implements SDKConnectorInterface {

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
   * The http client.
   *
   * @var \Apigee\Edge\HttpClient\ClientInterface
   */
  protected $client;

  /**
   * The Edge organization.
   *
   * @var string
   */
  protected $organization;

  /**
   * Constructs a new SDKConnector.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\apigee_edge\CredentialsStorageManager $credentials_storage_plugin_manager
   *   The manager for credentials storage plugins.
   * @param \Drupal\apigee_edge\AuthenticationMethodManager $authentication_method_plugin_manager
   *   The manager for authentication method plugins.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, CredentialsStorageManager $credentials_storage_plugin_manager, AuthenticationMethodManager $authentication_method_plugin_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->credentialsStoragePlugin = $credentials_storage_plugin_manager->createInstance($config_factory->get('apigee_edge.credentials_storage')->get('credentials_storage_type'));
    $this->authenticationMethodPlugin = $authentication_method_plugin_manager->createInstance($config_factory->get('apigee_edge.authentication_method')->get('authentication_method'));
    $credentials = $this->credentialsStoragePlugin->loadCredentials();
    $auth = $this->authenticationMethodPlugin->createAuthenticationObject($credentials);
    $this->organization = $credentials->getOrganization();
    $this->client = new Client($auth, NULL, $credentials->getEndpoint());
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(): string {
    return $this->organization;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient() : ClientInterface {
    return $this->client;
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
    $credentials = $credentials ?? $this->credentialsStoragePlugin->loadCredentials();
    $auth = $this->authenticationMethodPlugin->createAuthenticationObject($credentials);
    $client = new Client($auth, NULL, $credentials->getEndpoint());
    $oc = new OrganizationController($client);
    $oc->load($credentials->getOrganization());
  }

}
