<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppController;
use Drupal\apigee_edge\SDKConnectorInterface;

class DeveloperAppStorage extends EdgeEntityStorageBase implements DeveloperAppStorageInterface {

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
     $ids = array_map(function($entity) {
       /** @var \Apigee\Edge\Api\Management\Entity\DeveloperApp $entity */
       return $entity->getAppId();
     }, $controller->getEntitiesByDeveloper($developerId));
     return $this->loadMultiple(array_values($ids));
  }

}
