<?php

namespace Drupal\apigee_edge;

use Apigee\Edge\Api\Management\Controller\DeveloperController;
use Apigee\Edge\Entity\EntityCrudOperationsControllerInterface;
use Apigee\Edge\Exception\UnknownEndpointException;
use Apigee\Edge\HttpClient\Client;
use Apigee\Edge\HttpClient\ClientInterface;
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
    $this->client = new Client($auth);
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
    switch ($entity_type) {
      case 'developer':
        return $this->entityTypeManager->getStorage('developer')->getController($this);

      default:
        throw new UnknownEndpointException($entity_type);
    }
  }

}
