<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Entity\CpsLimitEntityControllerInterface;
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
    $this->withController(function (CpsLimitEntityControllerInterface $controller) use ($ids, &$loaded) {
      $entities = [];
      foreach ($controller->getEntities() as $entity) {
        /** @var \Apigee\Edge\Entity\EntityInterface $entity */
        $drupal_entity = call_user_func([$this->entityClass, 'createFromEdgeEntity'], $entity);
        $entities[$entity->id()] = $drupal_entity;

        $this->setStaticCache($entities);

        if (in_array($entity->id(), $ids)) {
          $loaded[$entity->id()] = $drupal_entity;
        }
      }
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
    $this->withController(function (CpsLimitEntityControllerInterface $controller) use ($entities) {
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
    /** @var \Drupal\apigee_edge\Entity\EdgeEntityBase $entity */
    $this->withController(function (CpsLimitEntityControllerInterface $controller) use ($id, $entity, &$result) {

    });
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
  }

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
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision($revision_id) {
  }

  abstract protected function getController() : CpsLimitEntityControllerInterface;

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
