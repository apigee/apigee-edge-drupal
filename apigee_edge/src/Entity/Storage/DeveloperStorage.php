<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Api\Management\Controller\DeveloperController;
use Apigee\Edge\Api\Management\Controller\DeveloperControllerInterface;
use Apigee\Edge\Entity\EntityCrudOperationsControllerInterface;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Controller class for developers.
 */
class DeveloperStorage extends EdgeEntityStorageBase implements DeveloperStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getController(SDKConnectorInterface $connector) : EntityCrudOperationsControllerInterface {
    return new DeveloperController($connector->getOrganization(), $connector->getClient());
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\Developer $entity */
    $developer_status = $entity->getStatus();
    $result = parent::doSave($id, $entity);

    // In case of entity update, the original email must be
    // replaced by the new email before a new API call.
    if ($result === SAVED_UPDATED) {
      $entity->setOriginalEmail($entity->getEmail());
    }

    $this->withController(function (DeveloperControllerInterface $controller) use ($entity, $developer_status) {
      $controller->setStatus($entity->id(), $developer_status);
    });

    return $result;
  }

}
