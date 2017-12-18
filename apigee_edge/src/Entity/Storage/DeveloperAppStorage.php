<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Api\Management\Controller\AppController;
use Apigee\Edge\Api\Management\Controller\DeveloperAppController;
use Apigee\Edge\Api\Management\Controller\DeveloperAppControllerInterface;
use Apigee\Edge\Entity\CpsListingEntityControllerInterface;
use Apigee\Edge\Api\Management\Entity\DeveloperApp as EdgeDeveloperApp;
use Apigee\Edge\Entity\EntityCrudOperationsControllerInterface;
use Apigee\Edge\Entity\EntityInterface;
use Apigee\Edge\Structure\CpsListLimitInterface;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\apigee_edge\SDKConnectorInterface;

class DeveloperAppStorage extends EdgeEntityStorageBase implements DeveloperAppStorageInterface {

  public function getController(SDKConnectorInterface $connector) : EntityCrudOperationsControllerInterface {
    return new class($connector->getOrganization(), $connector->getClient()) extends AppController implements EntityCrudOperationsControllerInterface, CpsListingEntityControllerInterface {

      /**
       * {@inheritdoc}
       */
      public function load(string $entityId) : EntityInterface {
        return $this->loadApp($entityId);
      }

      protected function createDeveloperController(EdgeDeveloperApp $app) : DeveloperAppControllerInterface {
        return new DeveloperAppController($this->getOrganisation(), $app->getDeveloperId(), $this->client);
      }

      /**
       * {@inheritdoc}
       */
      public function create(EntityInterface $entity) : void {
        /** @var DeveloperApp $entity */
        $controller = $this->createDeveloperController($entity);
        $controller->create($entity);
      }

      /**
       * {@inheritdoc}
       */
      public function update(EntityInterface $entity) : void {
        /** @var DeveloperApp $entity */
        $controller = $this->createDeveloperController($entity);
        $controller->update($entity);
      }

      /**
       * {@inheritdoc}
       */
      public function delete(string $entityId) : EntityInterface {
        /** @var DeveloperApp $entity */
        $entity = $this->loadApp($entityId);
        $controller = $this->createDeveloperController($entity);
        return $controller->delete($entity->id());
      }

      /**
       * {@inheritdoc}
       */
      public function getEntities(CpsListLimitInterface $cpsLimit = NULL): array {
        return $this->listApps(TRUE, $cpsLimit);
      }

      /**
       * {@inheritdoc}
       */
      public function getEntityIds(CpsListLimitInterface $cpsLimit = NULL): array {
        return $this->listAppIds($cpsLimit);
      }

    };
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    $map = [];
    /** @var DeveloperApp $entity */
    foreach ($entities as $entity) {
      $map[$entity->getAppId()] = $entity->getName();
      $entity->setName($entity->getAppId());
    }

    try {
      parent::doDelete($entities);
    }
    finally {
      foreach ($entities as $entity) {
        $entity->setName($map[$entity->getAppId()]);
      }
    }
  }

}
