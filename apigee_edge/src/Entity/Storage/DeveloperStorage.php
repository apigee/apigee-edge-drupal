<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Api\Management\Controller\DeveloperControllerInterface;
use Apigee\Edge\Entity\EntityCrudOperationsControllerInterface;
use Drupal\Core\Entity\EntityInterface;


/**
 * Controller class for developers.
 */
class DeveloperStorage extends EdgeEntityStorageBase implements DeveloperStorageInterface {

  /**
   * {@inheritdoc}
   */
  protected function getController() : EntityCrudOperationsControllerInterface {
    return $this->connector->getDeveloperController();
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    $result = parent::doSave($id, $entity);

    $this->withController(function (DeveloperControllerInterface $controller) use ($entity) {
      /** @var \Drupal\apigee_edge\Entity\Developer $entity */
      $controller->setStatus($entity->id(), $entity->getStatus());
    });

    return $result;
  }

}
