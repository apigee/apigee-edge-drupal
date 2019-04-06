<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Entity\EntityInterface as SdkEntityInterface;
use Apigee\Edge\Exception\ApiException;
use Drupal\apigee_edge\Entity\Controller\EdgeEntityControllerInterface;
use Drupal\apigee_edge\Entity\Controller\EntityCacheAwareControllerInterface;
use Drupal\apigee_edge\Entity\EdgeEntityInterface as DrupalEdgeEntityInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageBase as DrupalEntityStorageBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base entity storage class for Apigee Edge entities.
 */
abstract class EdgeEntityStorageBase extends DrupalEntityStorageBase implements EdgeEntityStorageInterface {

  /**
   * Initial status for saving a item to Apigee Edge.
   *
   * Similar to SAVED_NEW and SAVED_UPDATED. If this is returned then
   * something probably went wrong.
   *
   * @var int
   */
  public const SAVED_UNKNOWN = 0;

  /**
   * Cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Number of seconds until an entity can be served from cache.
   *
   * -1 is also an allowed, which means the item should never be removed unless
   * explicitly deleted.
   *
   * @var int
   */
  protected $cacheExpiration = CacheBackendInterface::CACHE_PERMANENT;

  /**
   * The system time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $systemTime;

  /**
   * Constructs an EdgeEntityStorageBase instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to be used.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache.
   * @param \Drupal\Component\Datetime\TimeInterface $system_time
   *   The system time.
   */
  public function __construct(EntityTypeInterface $entity_type, CacheBackendInterface $cache_backend, MemoryCacheInterface $memory_cache, TimeInterface $system_time) {
    parent::__construct($entity_type, $memory_cache);
    $this->cacheBackend = $cache_backend;
    $this->systemTime = $system_time;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('cache.apigee_edge_entity'),
      $container->get('entity.memory_cache'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
    // Attempt to load entities from the persistent cache. This will remove IDs
    // that were loaded from $ids.
    $entities_from_cache = $this->getFromPersistentCache($ids);
    return $entities_from_cache + $this->getFromStorage($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function loadUnchanged($id) {
    $this->resetControllerCache([$id]);
    return parent::loadUnchanged($id);
  }

  /**
   * Resets entity controller's cache if it is a cached entity controller.
   *
   * @param string[] $ids
   *   Array of entity ids.
   */
  protected function resetControllerCache(array $ids) {
    $controller = $this->entityController();
    if ($controller instanceof EntityCacheAwareControllerInterface) {
      $controller->entityCache()->removeEntities($ids);
    }
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
    $this->withController(function (EdgeEntityControllerInterface $controller) use ($entities) {
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
    $result = static::SAVED_UNKNOWN;
    $this->withController(function (EdgeEntityControllerInterface $controller) use ($id, $entity, &$result) {
      /** @var \Drupal\apigee_edge\Entity\EdgeEntityInterface $entity */
      if ($entity->isNew()) {
        $controller->create($entity->decorated());
        $result = SAVED_NEW;
      }
      else {
        $controller->update($entity->decorated());
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
  public function loadRevision($revision_id) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision($revision_id) {}

  /**
   * Returns the wrapped controller instance used by this storage.
   *
   * @return \Drupal\apigee_edge\Entity\Controller\EdgeEntityControllerInterface
   *   The entity controller interface with CRUDL capabilities.
   */
  abstract protected function entityController(): EdgeEntityControllerInterface;

  /**
   * Wraps communication with Apigee Edge.
   *
   * This function converts exceptions from Apigee Edge into
   * EntityStorageException and logs the original exceptions.
   *
   * @param callable $action
   *   Communication to perform.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   The converted exception.
   */
  protected function withController(callable $action) {
    try {
      $action($this->entityController());
    }
    catch (\Exception $ex) {
      throw new EntityStorageException($ex->getMessage(), $ex->getCode(), $ex);
    }
  }

  /**
   * Creates a new Drupal entity from an SDK entity.
   *
   * @param \Apigee\Edge\Entity\EntityInterface $sdk_entity
   *   An SDK entity.
   *
   * @return \Drupal\apigee_edge\Entity\EdgeEntityInterface
   *   The Drupal entity that decorates the SDK entity.
   */
  protected function createNewInstance(SdkEntityInterface $sdk_entity): DrupalEdgeEntityInterface {
    $rc = new \ReflectionClass($this->entityClass);
    $rm = $rc->getMethod('createFrom');
    return $rm->invoke(NULL, $sdk_entity);
  }

  /**
   * Gets entities from the storage.
   *
   * @param array|null $ids
   *   If not empty, return entities that match these IDs. Return all entities
   *   when NULL.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of entities from the storage.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getFromStorage(array $ids = NULL) {
    $entities = [];

    // If ids is an empty array there is nothing to do.
    // Probably every entities could have been found in the persistent cache.
    // Node::loadMultiple() works the same.
    if ($ids === []) {
      return $entities;
    }

    $this->withController(function (EdgeEntityControllerInterface $controller) use ($ids, &$entities) {
      $tmp = [];
      // Speed up things by loading only one entity.
      if ($ids !== NULL && count($ids) === 1) {
        // TODO When user's email changes do not ask Apigee Edge 3 times
        // whether a developer exists with the new email address or not.
        try {
          $entity = $controller->load(reset($ids));
          $tmp[$entity->id()] = $entity;
        }
        catch (ApiException $e) {
          // Entity with id may not exists.
        }
      }
      else {
        // There is nothing else we could do we have to load all entities
        // from Apigee Edge.
        $tmp = $controller->loadAll();
      }

      $entities = $this->processLoadedEntities($ids, $tmp);
    });

    return $entities;
  }

  /**
   * Processes loaded (SDK) entities to Drupal entities.
   *
   * This method also ensured that storage hooks gets called and entities
   * gets saved to the persistent cache before they gets returned.
   *
   * @param array|null $ids
   *   Originally request entity ids.
   * @param array $sdk_entities
   *   The loaded SDK entities by the entity controller for the requested ids.
   *
   * @return array
   *   Array of Drupal entities.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   If Drupal entity ids could not be resolved.
   */
  final protected function processLoadedEntities(?array $ids, array $sdk_entities): array {
    $entities = [];
    // Returned entities are SDK entities and not Drupal entities,
    // what if the id is used in Drupal is different than what
    // SDK uses? (ex.: developer)
    foreach ($sdk_entities as $entity) {
      $drupal_entity = $this->createNewInstance($entity);
      if ($ids === NULL) {
        $entities[$drupal_entity->id()] = $drupal_entity;
      }
      elseif ($referenced_ids = array_intersect($drupal_entity->uniqueIds(), $ids)) {
        if (count($referenced_ids) > 1) {
          // Sanity check, why would someone try to load the same entity
          // by using more than one of its unique id.
          throw new EntityStorageException(sprintf('The same entity should be referenced only with one id, got %s.', implode('', $referenced_ids)));
        }
        $entities[reset($referenced_ids)] = $drupal_entity;
      }
    }
    $this->invokeStorageLoadHook($entities);
    $this->setPersistentCache($entities);

    return $entities;
  }

  /**
   * Invokes hook_entity_storage_load().
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   List of entities, keyed on the entity ID.
   */
  protected function invokeStorageLoadHook(array &$entities) {
    if (!empty($entities)) {
      // Call hook_entity_storage_load().
      foreach ($this->moduleHandler()->getImplementations('entity_storage_load') as $module) {
        $function = $module . '_entity_storage_load';
        $function($entities, $this->entityTypeId);
      }
      // Call hook_TYPE_storage_load().
      foreach ($this->moduleHandler()->getImplementations($this->entityTypeId . '_storage_load') as $module) {
        $function = $module . '_' . $this->entityTypeId . '_storage_load';
        $function($entities);
      }
    }
  }

  /**
   * Gets entities from the persistent cache backend.
   *
   * @param array|null &$ids
   *   If not empty, return entities that match these IDs. IDs that were found
   *   will be removed from the list.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of entities from the persistent cache.
   */
  protected function getFromPersistentCache(array &$ids = NULL) {
    if (!$this->entityType->isPersistentlyCacheable() || empty($ids)) {
      return [];
    }
    $entities = [];
    // Build the list of cache entries to retrieve.
    $cid_map = [];
    foreach ($ids as $id) {
      $cid_map[$id] = $this->buildCacheId($id);
    }
    $cids = array_values($cid_map);
    if ($cache = $this->cacheBackend->getMultiple($cids)) {
      // Get the entities that were found in the cache.
      foreach ($ids as $index => $id) {
        $cid = $cid_map[$id];
        if (isset($cache[$cid])) {
          $entities[$id] = $cache[$cid]->data;
          unset($ids[$index]);
        }
      }
    }
    return $entities;
  }

  /**
   * Stores entities in the persistent cache backend.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   Entities to store in the cache.
   */
  protected function setPersistentCache(array $entities) {
    if (!$this->entityType->isPersistentlyCacheable()) {
      return;
    }

    foreach ($entities as $id => $entity) {
      $this->cacheBackend->set($this->buildCacheId($id), $entity, $this->getPersistentCacheExpiration(), $this->getPersistentCacheTags($entity));
    }
  }

  /**
   * Number of seconds after a cache item expires.
   *
   * So our "persistent cache" implementation could be actually not a persistent
   * one but we kept using this naming convention by hoping that the persistent
   * caching features becomes decoupled from ContentEntityStorageBase and we
   * could build on the top of that solution with as less pain as possible.
   *
   * @return int
   *   Number of seconds after a cache item expires.
   */
  protected function getPersistentCacheExpiration() {
    if ($this->cacheExpiration !== CacheBackendInterface::CACHE_PERMANENT) {
      return $this->systemTime->getCurrentTime() + $this->cacheExpiration;
    }
    return $this->cacheExpiration;
  }

  /**
   * Generates cache tags for entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity object.
   *
   * @return array
   *   Array of cache tags.
   */
  protected function getPersistentCacheTags(EntityInterface $entity) {
    return [
      "{$this->entityTypeId}",
      "{$this->entityTypeId}:values",
      "{$this->entityTypeId}:{$entity->id()}",
      "{$this->entityTypeId}:{$entity->id()}:values",
    ];
  }

  /**
   * Builds the cache ID for the passed in entity ID.
   *
   * @param int $id
   *   Entity ID for which the cache ID should be built.
   *
   * @return string
   *   Cache ID that can be passed to the cache backend.
   */
  protected function buildCacheId($id) {
    return "values:{$this->entityTypeId}:{$id}";
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(array $ids = NULL) {
    if ($this->entityType->isStaticallyCacheable() && $ids) {
      $cids = [];
      foreach ($ids as $id) {
        $cid = $this->buildCacheId($id);
        $cids[] = $cid;
        $this->memoryCache->delete($cid);
      }
      if ($this->entityType->isPersistentlyCacheable()) {
        $this->cacheBackend->deleteMultiple($cids);
      }
    }
    else {
      $this->memoryCache->invalidateTags([$this->memoryCacheTag]);
      if ($this->entityType->isPersistentlyCacheable()) {
        Cache::invalidateTags([$this->entityTypeId . ':values']);
      }
    }
    // We do not clear the entity controller's cache here because our main goal
    // with the entity controller cache to reduce the API calls that we
    // send to Apigee Edge. Although we do delete the entity controller's cache
    // when it is necessary, like in loadUnchanged().
  }

}
