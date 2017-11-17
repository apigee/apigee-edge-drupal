<?php

namespace Drupal\apigee_edge;

use \Apigee\Edge\Entity\EntityControllerFactory;
use \Apigee\Edge\HttpClient\Client;

/**
 * Provides an Apigee Edge SDK connector.
 */
class SDKConnector {

  /**
   * Gets the EntityControllerFactory object.
   *
   * Creates an EntityControllerFactory object using the stored credentials
   * and the configured authentication method.
   *
   * @return EntityControllerFactory
   *   The EntityControllerFactory object.
   */
  public function getEntityControllerFactory() : EntityControllerFactory {
    $credentials_storage_plugin_manager = \Drupal::service('plugin.manager.credentials_storage');
    $authentication_method_plugin_manager = \Drupal::service('plugin.manager.authentication_method');
    $credentials_storage_config = \Drupal::config('apigee_edge.credentials_storage');
    $authentication_method_config = \Drupal::config('apigee_edge.authentication_method');

    /** @var CredentialsStoragePluginInterface $credentials_storage_plugin */
    $credentials_storage_plugin = $credentials_storage_plugin_manager->createInstance($credentials_storage_config->get('credentials_storage_type'));
    /** @var AuthenticationMethodPluginInterface $authentication_method_plugin */
    $authentication_method_plugin = $authentication_method_plugin_manager->createInstance($authentication_method_config->get('authentication_method'));

    $credentials = $credentials_storage_plugin->loadCredentials();
    $auth = $authentication_method_plugin->createAuthenticationObject($credentials);
    $client = new Client($auth);

    return new EntityControllerFactory($credentials->getBaseURL(), $client);
  }

}
