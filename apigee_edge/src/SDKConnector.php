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
   * The http client.
   *
   * @var \Apigee\Edge\HttpClient\Client
   */
  protected $client;

  /**
   * The Edge organization.
   *
   * @var string
   */
  protected $organization;

  /**
   * The DeveloperController object.
   *
   * @var \Apigee\Edge\Api\Management\Controller\DeveloperController
   */
  protected $developerController;

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
    $this->credentialsStoragePlugin = $credentials_storage_plugin_manager->createInstance($config_factory->get('apigee_edge.credentials_storage')->get('credentials_storage_type'));
    $this->authenticationMethodPlugin = $authentication_method_plugin_manager->createInstance($config_factory->get('apigee_edge.authentication_method')->get('authentication_method'));
    $credentials = $this->credentialsStoragePlugin->loadCredentials();
    $auth = $this->authenticationMethodPlugin->createAuthenticationObject($credentials);
    $this->organization = $credentials->getOrganization();
    $this->client = new Client($auth);
  }

  /**
   * Returns the http client.
   *
   * @return \Apigee\Edge\HttpClient\Client
   *   The http client.
   */
  public function getClient() : Client {
    return $this->client;
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
    if (!$this->developerController) {
      $this->developerController = new DeveloperController($this->organization, $this->client);
    }

    return $this->developerController;
  }

}
