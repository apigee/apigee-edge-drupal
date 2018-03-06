<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Apigee\Edge\Entity\EntityDenormalizer;
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Apigee\Edge\Entity\EntityNormalizer;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Edge entity storage handlers.
 */
abstract class EdgeEntityStorageBase extends EntityStorageBase implements EdgeEntityStorageInterface {

  /**
   * Static cache of (all) entities that have been loaded in the same request.
   *
   * Keys are entity ids. W can not query Apigee Edge like a database backend
   * therefore we have to load all entities at once and applying filter criteria
   * on them in PHP.
   * Drupal entity's built in static cache solution only stores the necessary
   * amount of entities. In another words, it only stores those entities that
   * a previously executed database query returned. A database query only
   * returns those entities that matched for the provided criteria.
   * On the contrary, our Edge entity query class loads all entities
   * from Apigee Edge first and filters the returned result that Drupal's built
   * in static cache stores in the end.
   *
   * @var array
   *
   * @see \Drupal\apigee_edge\Entity\Query\Query::execute()
   */
  protected $entityCache = [];

  /**
   * Tells whether all entities has been loaded from Apigee Edge before.
   *
   * @var bool
   */
  protected $allEntitiesInEntityCache = FALSE;

  /**
   * The service container this object should use.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

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
    return new static($container, $entity_type, $logger_factory->get('edge_entity'));
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
    $loaded = [];

    // Create a new variable which is either a prepared version of the $ids
    // array for later comparison with the entity cache, or FALSE if no $ids
    // were passed. The $ids array is reduced as items are loaded from cache,
    // and we need to know if it's empty for this reason to avoid querying the
    // database when all requested entities are loaded from cache.
    $passed_ids = !empty($ids) ? array_flip($ids) : FALSE;
    // Try to load entities from the static cache, if the entity type supports
    // static caching.
    if ($this->entityType->isStaticallyCacheable()) {
      if ($this->allEntitiesInEntityCache) {
        return $this->entityCache;
      }
      elseif ($ids) {
        $loaded += $this->getFromAllEntityStaticCache($ids);
        // If any entities were loaded, remove them from the ids still to load.
        if ($passed_ids) {
          $ids = array_keys(array_diff_key($passed_ids, $loaded));
        }
      }
    }

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
      // Store (all) loaded entities from our own static cache.
      $this->setEdgeEntityStaticCache($entities);
    });

    if ($ids === NULL || !$ids) {
      $this->allEntitiesInEntityCache = TRUE;
    }

    return $loaded;
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
  public function resetCache(array $ids = NULL) {
    // We must clear our own static cache too.
    $this->resetAllEntityCache($ids);
    parent::resetCache($ids);
  }

  /**
   * Resets the internal, static all entity cache.
   *
   * @param array $ids
   *   (optional) If specified, the cache is reset for the entities with the
   *   given ids only.
   */
  public function resetAllEntityCache(array $ids = NULL) {
    // Tell doLoadMultiple() to load all entities from it is called with
    // empty $ids parameter.
    $this->allEntitiesInEntityCache = FALSE;
    if ($this->entityType->isStaticallyCacheable() && isset($ids)) {
      foreach ($ids as $id) {
        unset($this->entityCache[$id]);
      }
    }
    else {
      $this->entityCache = [];
    }
  }

  /**
   * Gets entities from the static cache.
   *
   * @param array $ids
   *   If not empty, return entities that match these IDs.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of entities from the all entity cache.
   */
  protected function getFromAllEntityStaticCache(array $ids) {
    $entities = [];
    // Load any available entities from the internal cache.
    if ($this->entityType->isStaticallyCacheable() && !empty($this->entityCache)) {
      $entities += array_intersect_key($this->entityCache, array_flip($ids));
    }
    return $entities;
  }

  /**
   * Stores entities in the static entity cache.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   Entities to store in the cache.
   */
  protected function setEdgeEntityStaticCache(array $entities) {
    if ($this->entityType->isStaticallyCacheable()) {
      $this->entityCache += $entities;
    }
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
    catch (\Exception $ex) {
      throw new EntityStorageException($ex->getMessage(), $ex->getCode(), $ex);
    }
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

}
