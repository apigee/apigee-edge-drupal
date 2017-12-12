<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Entity\EntityCrudOperationsControllerInterface;
use Apigee\Edge\Entity\EntityDenormalizer;
use Apigee\Edge\Entity\EntityNormalizer;
use Drupal\apigee_edge\ExceptionLoggerTrait;
use Drupal\apigee_edge\SDKConnector;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Edge entity storage handlers.
 */
abstract class EdgeEntityStorageBase extends EntityStorageBase implements EdgeEntityStorageInterface {

  use ExceptionLoggerTrait;

  /**
   * @var \Drupal\apigee_edge\SDKConnector
   */
  protected $connector;

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
    $loaded = [];
    $this->withController(function ($controller) use ($ids, &$loaded) {
      /** @var \Apigee\Edge\Entity\CpsListingEntityControllerInterface|\Apigee\Edge\Entity\NonCpsListingEntityControllerInterface $controller */
      $entities = [];
      $normalizer = new EntityNormalizer();
      $denormalizer = new EntityDenormalizer();
      foreach ($controller->getEntities() as $edge_entity) {
        /** @var \Apigee\Edge\Entity\EntityInterface $edge_entity */
        $normalized = $normalizer->normalize($edge_entity);
        /** @var EntityInterface $drupal_entity */
        $drupal_entity = $denormalizer->denormalize($normalized, $this->entityClass);

        $entities[$drupal_entity->id()] = $drupal_entity;

        if (in_array($drupal_entity->id(), $ids)) {
          $loaded[$drupal_entity->id()] = $drupal_entity;
        }
      }

      $this->setStaticCache($entities);
    });

    return $loaded;
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) {
    return (bool) $this->load($id);
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    $this->withController(function (EntityCrudOperationsControllerInterface $controller) use ($entities) {
      foreach ($entities as $entity) {
        /** @var EntityInterface $entity */
        $controller->delete($entity->id());
      }
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    $result = 0;
    /** @var \Apigee\Edge\Entity\EntityInterface $entity */
    $this->withController(function (EntityCrudOperationsControllerInterface $controller) use ($id, $entity, &$result) {
      if ($entity->isNew()) {
        $controller->create($entity);
        $result = SAVED_NEW;
      }
      else {
        $controller->update($entity);
        $result = SAVED_UPDATED;
      }
    });

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'entity.query.edge';
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, SDKConnector $connector, LoggerInterface $logger) {
    parent::__construct($entity_type);
    $this->connector = $connector;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    /** @var SDKConnector $connector */
    $connector = $container->get('apigee_edge.sdk_connector');

    /** @var \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory */
    $logger_factory = $container->get('logger.factory');

    return new static(
      $entity_type,
      $connector,
      $logger_factory->get('edge_entity')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function loadRevision($revision_id) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision($revision_id) {
    return NULL;
  }

  /**
   * Returns the controller for the current entity.
   *
   * @return \Apigee\Edge\Entity\EntityCrudOperationsControllerInterface
   *   The controller must also implement CpsListingEntityControllerInterface
   *   or NonCpsListingEntityControllerInterface.
   */
  abstract protected function getController() : EntityCrudOperationsControllerInterface;

  /**
   * Wraps communication with edge.
   *
   * This function converts exceptions from edge into EntityStorageException and
   * logs the original exceptions.
   *
   * @param callable $action
   *   Communication to perform.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   The converted exception.
   */
  protected function withController(callable $action) {
    try {
      $action($this->getController());
    }
    catch (ClientException $ex) {
      $this->logException($ex);
      throw new EntityStorageException($ex->getMessage(), $ex->getCode(), $ex);
    }
    catch (\Exception $ex) {
      $this->logException($ex);
      throw new EntityStorageException($ex->getMessage(), $ex->getCode(), $ex);
    }
  }

}
