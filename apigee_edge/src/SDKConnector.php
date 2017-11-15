<?php

namespace Drupal\apigee_edge;

use \Apigee\Edge\Entity\EntityControllerFactory;
use \Apigee\Edge\HttpClient\Client;

/**
 * Defines an interface for credentials classes.
 */
class SDKConnector {

  /**
   * Creates an EntityControllerFactory object using the
   * stored credentials and the configured authentication method.
   *
   * @return EntityControllerFactory.
   */
  public static function getEntityControllerFactory() : EntityControllerFactory {
    $credentials_storage_plugin_type = \Drupal::service('plugin.manager.credentials_storage');
    $authentication_method_plugin_type = \Drupal::service('plugin.manager.authentication_method');
    $credentials_storage_config = \Drupal::config('apigee_edge.credentials_storage');
    $authentication_method_config = \Drupal::config('apigee_edge.authentication_method');

    $credentials_storage_plugin = $credentials_storage_plugin_type->createInstance($credentials_storage_config->get('credentials_storage_type'));
    $authentication_method_plugin = $authentication_method_plugin_type->createInstance($authentication_method_config->get('authentication_method'));

    $credentials = $credentials_storage_plugin->loadCredentials();
    $auth = $authentication_method_plugin->createAuthenticationObject($credentials);
    $client = new Client($auth);

    return new EntityControllerFactory($credentials->getBaseURL(), $client);
  }
}