<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Apigee\Edge\Entity\EntityDenormalizer;
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Apigee\Edge\Entity\EntityNormalizer;
use Drupal\apigee_edge\ExceptionLoggerTrait;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageBase;
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
   * The service container this object should use.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
    $loaded = [];
    $this->withController(function ($controller) use ($ids, &$loaded) {
      /** @var \Apigee\Edge\Controller\CpsListingEntityControllerInterface|\Apigee\Edge\Controller\NonCpsListingEntityControllerInterface $controller */
      $entities = [];
      /** @var \Apigee\Edge\Entity\EntityInterface $edge_entity */
      foreach ($controller->getEntities() as $edge_entity) {
        /** @var \Drupal\Core\Entity\EntityInterface $drupal_entity */
        $drupal_entity = $this->toDrupalEntity($edge_entity);
        $entities[$drupal_entity->id()] = $drupal_entity;
        if ($ids === NULL || in_array($drupal_entity->id(), $ids)) {
          $loaded[$drupal_entity->id()] = $drupal_entity;
        }
      }

      // TODO Store in a static cache those entities that have been loaded here
      // but has not been returned by this function.

    });

    return $loaded;
  }

  /**
   * Transforms an SDK entity to a Drupal entity.
   *
   * @param \Apigee\Edge\Entity\EntityInterface $edge_entity
   *   SDK entity object.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Drupal entity.
   */
  protected function toDrupalEntity(EdgeEntityInterface $edge_entity) {
    $normalizer = new EntityNormalizer();
    $denormalizer = new EntityDenormalizer();
    /** @var \Apigee\Edge\Entity\EntityInterface $edge_entity */
    $normalized = $normalizer->normalize($edge_entity);
    /** @var \Drupal\Core\Entity\EntityInterface $drupal_entity */
    return $denormalizer->denormalize($normalized, $this->entityClass);
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) {
    return !$entity->isNew();
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    $this->withController(function (EntityCrudOperationsControllerInterface $controller) use ($entities) {
      foreach ($entities as $entity) {
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
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
  public function __construct(ContainerInterface $container, EntityTypeInterface $entity_type, LoggerInterface $logger) {
    parent::__construct($entity_type);
    $this->container = $container;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory */
    $logger_factory = $container->get('logger.factory');

    return new static(
      $container,
      $entity_type,
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
   * Gets the SDK connector.
   *
   * @return \Drupal\apigee_edge\SDKConnectorInterface
   *   The SDK connector.
   */
  protected function getConnector() : SDKConnectorInterface {
    /** @var \Drupal\apigee_edge\SDKConnectorInterface $connector */
    static $connector;
    if (!$connector) {
      $connector = $this->container->get('apigee_edge.sdk_connector');
    }

    return $connector;
  }

  /**
   * Wraps communication with Apigee Edge.
   *
   * This function converts exceptions from Edge into EntityStorageException and
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
      $action($this->getController($this->getConnector()));
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
