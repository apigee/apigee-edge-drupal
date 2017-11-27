<?php

namespace Drupal\apigee_edge;

use Apigee\Edge\Api\Management\Controller\DeveloperController;
use Apigee\Edge\HttpClient\Client;
use Drupal\Core\Config\ConfigFactoryInterface;

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
   * Constructs a new SDKConnector.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\apigee_edge\CredentialsStorageManager $credentials_storage_plugin_manager
   *   The manager for credentials storage plugins.
   * @param \Drupal\apigee_edge\AuthenticationMethodManager $authentication_method_plugin_manager
   *   The manager for authentication method plugins.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CredentialsStorageManager $credentials_storage_plugin_manager, AuthenticationMethodManager $authentication_method_plugin_manager) {
    $this->credentialsStoragePlugin = $credentials_storage_plugin_manager->createInstance($config_factory->get('credentials_storage_type'));
    $this->authenticationMethodPlugin = $authentication_method_plugin_manager->createInstance($config_factory->get('authentication_method'));
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
