<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Api\Management\Controller\AppController;
use Apigee\Edge\Api\Management\Controller\DeveloperAppController;
use Apigee\Edge\Api\Management\Controller\DeveloperAppControllerInterface;
use Apigee\Edge\Controller\CpsListingEntityControllerInterface;
use Apigee\Edge\Api\Management\Entity\DeveloperApp as EdgeDeveloperApp;
use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Apigee\Edge\Entity\EntityDenormalizer;
use Apigee\Edge\Entity\EntityInterface;
use Apigee\Edge\Entity\EntityNormalizer;
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

      protected function convertEntity(DeveloperApp $app) : EdgeDeveloperApp {
        $normalizer = new EntityNormalizer();
        $denormalizer = new EntityDenormalizer();

        $normalized = $normalizer->normalize($app);
        //$normalized = json_decode(json_encode($normalized), TRUE);
        return $denormalizer->denormalize($normalized, EdgeDeveloperApp::class);
      }

      protected function applyChanges(DeveloperApp $destination, EdgeDeveloperApp $source) {
        $rodst = new \ReflectionObject($destination);
        $rosrc = new \ReflectionObject($source);
        foreach ($rodst->getProperties() as $property) {
          $setter = 'set' . ucfirst($property->getName());
          $getter = 'get' . ucfirst($property->getName());
          if ($rodst->hasMethod($setter) && $rosrc->hasMethod($getter)) {
            $rm =  new \ReflectionMethod($destination, $setter);
            $value = $source->{$getter}();
            if ($value !== NULL) {
              $rm->invoke($destination, $value);
            }
          }
        }
      }

      /**
       * {@inheritdoc}
       */
      public function create(EntityInterface $entity) : void {
        /** @var DeveloperApp $entity */
        $controller = $this->createDeveloperController($entity);
        $converted = $this->convertEntity($entity);
        $controller->create($converted);
        $this->applyChanges($entity, $converted);
      }

      /**
       * {@inheritdoc}
       */
      public function update(EntityInterface $entity) : void {
        /** @var DeveloperApp $entity */
        $controller = $this->createDeveloperController($entity);
        $converted = $this->convertEntity($entity);
        $controller->update($converted);
        $this->applyChanges($entity, $converted);
      }

      /**
       * {@inheritdoc}
       */
      public function delete(string $entityId) : EntityInterface {
        /** @var DeveloperApp $entity */
        $entity = $this->loadApp($entityId);
        $controller = $this->createDeveloperController($entity);
        return $controller->delete($entity->getName());
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

}
