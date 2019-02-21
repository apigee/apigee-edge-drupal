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

use Apigee\Edge\Api\Management\Entity\AppInterface;
use Apigee\Edge\Entity\EntityInterface;

/**
 * Default cache store for apps of a specific owner.
 *
 * The owner could be a developer or a company.
 *
 * See the interface definition for more details.
 *
 * All developers and companies have a dedicated instance from this cache.
 * (Therefore app names are unique as cache ids here.)
 *
 * @internal Do not create an instance from this directly. Always use the
 * factory.
 */
final class AppCacheByOwner implements AppCacheByOwnerInterface {

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
   * Developer id (UUID), email address or a company's company name.
   *
   * @var string
   */
  private $owner;

  /**
   * AppCacheByAppOwner constructor.
   *
   * @param string $owner
   *   Developer id (UUID), email address or a company's company name.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache
   *   The app cache service that stores app by their app id (UUID).
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner
   *   Dedicated cache instance that stores a specific owner app names.
   */
  public function __construct(string $owner, AppCacheInterface $app_cache, AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner) {
    $this->appCache = $app_cache;
    $this->appNameCache = $app_name_cache_by_owner->getAppNameCache($owner);
    $this->owner = $owner;
  }

  /**
   * {@inheritdoc}
   */
  public function saveEntities(array $entities): void {
    $this->appCache->saveEntities($entities);
    // $entity->id() returns app names so this is fine.
    $this->appNameCache->saveEntities($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function removeEntities(array $ids): void {
    $app_ids = $this->getAppIdsByAppNames($ids);
    $all_app_entities = $this->getEntities();
    $this->appCache->removeEntities($app_ids);
    $this->appNameCache->removeIds($ids);
    if (count($app_ids) === count($all_app_entities)) {
      $this->allEntitiesInCache(FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(array $ids = []): array {
    // If $ids is empty all entities should be returned.
    if (empty($ids)) {
      $apps = $this->appCache->getAppsByOwner($this->owner);
      return $apps ?? [];
    }

    return $this->getAppsByAppNames($ids);
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

  /**
   * Returns the app ids from the app cache for the given owner and app names.
   *
   * @param array $names
   *   Array of app names.
   *
   * @return array
   *   Array of app ids (UUIDs).
   */
  private function getAppIdsByAppNames(array $names): array {
    return array_map(function (AppInterface $app) {
      return $app->getAppId();
    }, $this->getAppsByAppNames($names));
  }

  /**
   * Returns the apps from the app cache for the given owner and app names.
   *
   * @param array $names
   *   Array of app names.
   *
   * @return \Apigee\Edge\Api\Management\Entity\AppInterface[]
   *   Array of apps.
   */
  private function getAppsByAppNames(array $names) : array {
    $apps = [];
    $apps_by_owner = $this->appCache->getAppsByOwner($this->owner);
    // There is nothing to invalidate.
    if ($apps_by_owner === NULL) {
      return $apps;
    }

    return array_filter($apps_by_owner, function (AppInterface $app) use ($names) {
      return in_array($app->getName(), $names);
    });
  }

}
