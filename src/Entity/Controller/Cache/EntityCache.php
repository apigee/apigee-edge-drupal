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

namespace Drupal\apigee_edge\Entity\Controller\Cache;

use Apigee\Edge\Entity\EntityInterface;
use Drupal\apigee_edge\MemoryCacheFactoryInterface;

/**
 * Default entity cache implementation for controllers.
 *
 * Always create a dedicated instance from this for an entity type!
 *
 * @internal
 */
class EntityCache implements EntityCacheInterface {

  /**
   * Array of entity ids stored in the cache.
   *
   * @var string[]
   */
  private $cacheIds = [];

  /**
   * Indicates whether all entities in the cache or not.
   *
   * @var bool
   */
  private $allEntitiesInCache = FALSE;

  /**
   * The memory cache backend used by this cache.
   *
   * It is easier to invalidate cache entries in a cache backend that supports
   * tags.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  protected $cacheBackend;

  /**
   * The entity id cache related to this entity type.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface
   */
  protected $entityIdCache;

  /**
   * EntityCache constructor.
   *
   * @param \Drupal\apigee_edge\MemoryCacheFactoryInterface $memory_cache_factory
   *   The memory cache factory service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface $entity_id_cache
   *   The related entity id cache.
   * @param string $entity_type
   *   The entity type.
   */
  public function __construct(MemoryCacheFactoryInterface $memory_cache_factory, EntityIdCacheInterface $entity_id_cache, string $entity_type) {
    $this->cacheBackend = $memory_cache_factory->get("{$entity_type}_entity_cache");
    $this->entityIdCache = $entity_id_cache;
  }

  /**
   * {@inheritdoc}
   */
  final public function saveEntities(array $entities): void {
    $items = [];
    foreach ($entities as $entity) {
      $primary_cache_item = $this->prepareCacheItem($entity);
      $primary_cache_cids = array_keys($primary_cache_item);
      $primary_cache_cid = reset($primary_cache_cids);
      $this->cacheIds[$primary_cache_cid] = $primary_cache_cid;
      $items += $primary_cache_item;
    }
    $this->cacheBackend->setMultiple($items);
    $this->entityIdCache->saveEntities($entities);
    $this->doSaveEntities($entities);
  }

  /**
   * Allows to perform additional tasks after entities got saved to cache.
   *
   * @param \Apigee\Edge\Entity\EntityInterface[] $entities
   *   Array of entities.
   */
  protected function doSaveEntities(array $entities): void {}

  /**
   * {@inheritdoc}
   */
  final public function removeEntities(array $ids): void {
    // Remove invalid ids because they cause PHP warnings/notices.
    // @see https://www.drupal.org/project/drupal/issues/3017753
    $sanitized_ids = array_intersect($ids, $this->cacheIds);
    $this->cacheIds = array_diff_key($this->cacheIds, array_flip($sanitized_ids));
    // If cacheIds is empty now, reset the state. Cache can be marked as
    // "complete" still by calling the setter method if needed.
    if (empty($this->cacheIds)) {
      $this->allEntitiesInCache = FALSE;
    }
    $this->cacheBackend->invalidateMultiple($sanitized_ids);
    $this->entityIdCache->removeIds($sanitized_ids);
    // Pass the original ids instead of the sanitized ones to child classes.
    // Because an id was not valid in this context it could be valid in that
    // context.
    $this->doRemoveEntities($ids);
  }

  /**
   * Allows to perform additional tasks after entities got deleted from cache.
   *
   * @param string[] $ids
   *   Array of entity ids.
   */
  protected function doRemoveEntities(array $ids): void {}

  /**
   * {@inheritdoc}
   */
  final public function getEntities(array $ids = []): array {
    if (empty($ids)) {
      $ids = $this->cacheIds;
    }

    return array_map(function ($item) {
      return $item->data;
    }, $this->cacheBackend->getMultiple($ids));
  }

  /**
   * {@inheritdoc}
   */
  final public function getEntity(string $id): ?EntityInterface {
    $entities = $this->getEntities([$id]);
    return $entities ? reset($entities) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  final public function allEntitiesInCache(bool $all_entities_in_cache): void {
    $this->allEntitiesInCache = $all_entities_in_cache;
    $this->entityIdCache->allIdsInCache($all_entities_in_cache);
  }

  /**
   * {@inheritdoc}
   */
  final public function isAllEntitiesInCache(): bool {
    return $this->allEntitiesInCache;
  }

  /**
   * Generates cache items for an entity.
   *
   * The cache id returned here must be always unique!
   *
   * @param \Apigee\Edge\Entity\EntityInterface $entity
   *   The entity object that gets cached.
   *
   * @return array
   *   Array of cache items. An array that CacheBackendInterface::setMultiple()
   *   can accept.
   *
   * @see \Drupal\Core\Cache\CacheBackendInterface::setMultiple()
   */
  protected function prepareCacheItem(EntityInterface $entity): array {
    return [
      $entity->id() => [
        'data' => $entity,
        'tags' => [$entity->id()],
      ],
    ];
  }

  /**
   * Prevents data stored in entity cache from being serialized.
   */
  public function __sleep() {
    return [];
  }

}
