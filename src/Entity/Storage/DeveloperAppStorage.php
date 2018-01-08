<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Api\Management\Controller\DeveloperController;
use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppController;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DeveloperAppStorage extends EdgeEntityStorageBase implements DeveloperAppStorageInterface {

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $entityTypeManager;

  /**
   * DeveloperAppStorage constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(ContainerInterface $container, EntityTypeInterface $entity_type, LoggerInterface $logger) {
    $this->entityTypeManager = $container->get('entity_type.manager');
    parent::__construct($container, $entity_type, $logger);
  }

  /**
   * @return \Apigee\Edge\Api\Management\Controller\DeveloperController
   */
  protected function getDeveloperController() {
    return new DeveloperController($this->getConnector()
      ->getOrganization(), $this->getConnector()->getClient());
  }

  /**
   * @method listByDeveloper
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *
   * @return \Apigee\Edge\Controller\EntityCrudOperationsControllerInterface
   */
  public function getController(SDKConnectorInterface $connector): EntityCrudOperationsControllerInterface {
    return new DeveloperAppController($connector->getOrganization(), $connector->getClient());
  }

  /**
   * {@inheritdoc}
   */
  public function loadByDeveloper(string $developerId): array {
    /** @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppControllerInterface $controller */
    $controller = $this->getController($this->getConnector());
    $ids = array_map(function ($entity) {
      /** @var \Apigee\Edge\Api\Management\Entity\DeveloperApp $entity */
      return $entity->getAppId();
    }, $controller->getEntitiesByDeveloper($developerId));
    return $this->loadMultiple(array_values($ids));
  }

  /**
   * {@inheritdoc}
   *
   * Adds Drupal user information to loaded entities.
   */
  protected function postLoad(array &$entities) {
    parent::postLoad($entities);
    $appid_developerid_map = [];
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    foreach ($entities as $entity) {
      $appid_developerid_map[$entity->getAppId()] = $entity->getDeveloperId();
    }

    $appid_developerid_map = array_unique($appid_developerid_map);
    $users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['apigee_edge_developer_id' => $appid_developerid_map]);
    $uid_devid_map = array_map(function (UserInterface $user) {
      return $user->get('apigee_edge_developer_id')->target_id;
    }, $users);
    $devid_uid_map = array_flip($uid_devid_map);

    foreach ($entities as $entity) {
      // If developer id is not in this map it means the developer does
      // not exist in Drupal yet (developer syncing between Edge and Drupal is
      // required) or the developer id has not been stored in
      // related Drupal user yet.
      // This can be fixed with running developer sync too,
      // because it could happen that the user had been
      // created in Drupal before Edge connected was configured.
      // Although, this could be a result of a previous error
      // but there should be a log about that.
      if (isset($devid_uid_map[$entity->getDeveloperId()])) {
        $entity->setOwnerId($devid_uid_map[$entity->getDeveloperId()]);
      }
    }
  }

}
