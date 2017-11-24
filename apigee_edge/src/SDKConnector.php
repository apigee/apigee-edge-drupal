<?php

namespace Drupal\apigee_edge;

use Apigee\Edge\Api\Management\Controller\DeveloperController;
use Apigee\Edge\HttpClient\Client;

/**
 * Provides an Apigee Edge SDK connector.
 */
class SDKConnector {

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
   * SDKConnector constructor.
   */
  public function __construct() {
    $credentials_storage_plugin_manager = \Drupal::service('plugin.manager.apigee_edge.credentials_storage');
    $authentication_method_plugin_manager = \Drupal::service('plugin.manager.apigee_edge.authentication_method');
    $credentials_storage_config = \Drupal::config('apigee_edge.credentials_storage');
    $authentication_method_config = \Drupal::config('apigee_edge.authentication_method');

    $this->credentialsStoragePlugin = $credentials_storage_plugin_manager->createInstance($credentials_storage_config->get('credentials_storage_type'));
    $this->authenticationMethodPlugin = $authentication_method_plugin_manager->createInstance($authentication_method_config->get('authentication_method'));
  }

  /**
   * Gets the DeveloperController object.
   *
   * Creates a DeveloperController object using the stored credentials
   * and the configured authentication method.
   *
   * @return DeveloperController
   *   The DeveloperController object.
   */
  public function getDeveloperController() : DeveloperController {
    $credentials = $this->credentialsStoragePlugin->loadCredentials();
    $auth = $this->authenticationMethodPlugin->createAuthenticationObject($credentials);
    $client = new Client($auth);
    return new DeveloperController($credentials->getOrganization(), $client);
  }

}
