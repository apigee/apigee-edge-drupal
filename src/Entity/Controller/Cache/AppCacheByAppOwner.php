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

/**
 * Default cache store for apps of a specific owner.
 *
 * Here all ids are an app names and not app UUIDs because all developers and
 * companies have a dedicated instance from this cache. (Therefore app names
 * are unique as cache ids here.)
 *
 * @internal Do not create an instance from this directly. Always use the
 * factory.
 */
final class AppCacheByAppOwner implements EntityCacheInterface {

  /**
   * An associative array where keys are app names an ids are app ids (UUIDs).
   *
   * @var array
   */
  private $appNameAppIdMap = [];

  /**
   * Indicates whether all entities in the cache or not.
   *
   * @var bool
   */
  private $allEntitiesInCache = FALSE;

  /**
   * The app cache service that stores app by their app id (UUID).
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface
   */
  private $appCache;

  /**
   * Dedicated cache instance that stores a specific owner app names.
   *
   * This cache is used by the getEntityIds() method on developer- and company
   * app controllers.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface
   */
  private $appNameCache;

  /**
   * AppCacheByAppOwner constructor.
   *
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache
   *   The app cache service that stores app by their app id (UUID).
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface $app_name_cache_by_owner
   *   Dedicated cache instance that stores a specific owner app names.
   */
  public function __construct(AppCacheInterface $app_cache, EntityIdCacheInterface $app_name_cache_by_owner) {
    $this->appCache = $app_cache;
    $this->appNameCache = $app_name_cache_by_owner;
  }

  /**
   * {@inheritdoc}
   */
  public function saveEntities(array $entities): void {
    $this->appCache->saveEntities($entities);
    /** @var \Apigee\Edge\Api\Management\Entity\AppInterface $entity */
    foreach ($entities as $entity) {
      $this->appNameAppIdMap[$entity->getName()] = $entity->getAppId();
    }
    // $entity->id() returns app names so this is fine.
    $this->appNameCache->saveEntities($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function removeEntities(array $ids): void {
    $flipped_ids = array_flip($ids);
    // Find app ids by app names.
    $app_ids = array_intersect_key($this->appNameAppIdMap, $flipped_ids);
    $this->appCache->removeEntities($app_ids);
    $this->appNameCache->removeIds($ids);
    // Remove app names from the internal cache.
    $this->appNameAppIdMap = array_diff_key($this->appNameAppIdMap, $flipped_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(array $ids = []): array {
    // If $ids is empty all entities should be returned.
    if (empty($ids)) {
      return $this->appCache->getEntities($this->appNameAppIdMap);
    }

    $app_ids = array_intersect_key($this->appNameAppIdMap, array_flip($ids));
    // Not all app names could be translated to app ids.
    // It is safer to return an empty result here and with that enforce
    // reload from Apigee Edge.
    if (empty($app_ids) || count($ids) !== $app_ids) {
      return [];
    }
    return $this->appCache->getEntities($app_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(string $id): ?EntityInterface {
    $entities = $this->getEntities([$id]);
    return $entities ? reset($entities) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function allEntitiesInCache(bool $all_entities_in_cache): void {
    $this->allEntitiesInCache = $all_entities_in_cache;
    $this->appNameCache->allIdsInCache($all_entities_in_cache);
  }

  /**
   * {@inheritdoc}
   */
  public function isAllEntitiesInCache(): bool {
    return $this->allEntitiesInCache;
  }

}
