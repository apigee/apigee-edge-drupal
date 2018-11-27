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
use Apigee\Edge\Api\Management\Entity\CompanyAppInterface;
use Apigee\Edge\Api\Management\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Exception\InvalidArgumentException;
use Drupal\apigee_edge\Exception\RuntimeException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;

/**
 * Default app cache implementation for developer- and company apps.
 *
 * @internal
 */
class AppCache implements AppCacheInterface {

  /**
   * The memory cache backend.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  protected $cacheBackend;

  /**
   * AppCache constructor.
   *
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $cache
   *   The memory cache backend used by the app cache.
   */
  public function __construct(MemoryCacheInterface $cache) {
    $this->cacheBackend = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function saveAppsToCache(array $apps): void {
    $app_cache_items = [];
    $owner_app_id_cache_items = [];
    foreach ($apps as $app) {
      $owner = $this->getAppOwner($app);
      // Add app to cache by app id.
      $app_cache_items[$app->getAppId()]['data'] = $app;
      // Tag it for easier invalidation. Owner here is either developer uuid
      // or company name.
      $app_cache_items[$app->getAppId()]['tags'] = [
        $app->getAppId(),
        $owner,
        $this->getAppType($app),
      ];

      // Store app name => app id (uuid) mapping in owner's cache.
      $owner_cid = $this->appOwnerCacheId($owner);
      // One-time owner cache item setup.
      if (!array_key_exists($owner_cid, $owner_app_id_cache_items)) {
        $owner_cache = $this->getAppIdsCacheByOwner($owner);
        // Owner's app name => app id cache is empty.
        if ($owner_cache === NULL) {
          // Tag the cache with the owner (developer id or company name) for
          // easier invalidation.
          $owner_app_id_cache_items[$owner_cid]['tags'] = [$owner_cid];
        }
        else {
          // Keep the previously set values in owner's cache. Do not override
          // them!
          $owner_app_id_cache_items[$owner_cid]['data'] = $owner_cache->data;
          $owner_app_id_cache_items[$owner_cid]['tags'] = $owner_cache->tags;
        }
      }
      // Store app name => app id (uuid) mapping in owner's cache.
      $owner_app_id_cache_items[$owner_cid]['data'][$app->getName()] = $app->getAppId();
    }
    $this->cacheBackend->setMultiple($app_cache_items);
    $this->cacheBackend->setMultiple($owner_app_id_cache_items);
  }

  /**
   * {@inheritdoc}
   */
  public function addAppIdsToCacheByOwner(string $owner, array $ids): void {
    $data = $this->getAppIdsCacheByOwner($owner);
    // Cache entry for this owner has not existed yet.
    if ($data === NULL) {
      $owner_cid = $this->appOwnerCacheId($owner);
      $data = (object) [
        'cid' => $owner_cid,
        'data' => [],
        'expire' => Cache::PERMANENT,
        'tags' => [$owner_cid],
      ];
    }
    // Sanity check.
    if (!is_array($data->data)) {
      $data->data = [];
    }
    $data->data += $ids;
    $this->cacheBackend->set($data->cid, $data->data, Cache::PERMANENT, $data->tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getAppFromCacheByAppId(string $appId): ?AppInterface {
    $data = $this->cacheBackend->get($appId);
    return $data ? $data->data : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAppIdsFromCacheByOwner(string $owner): ?array {
    $data = $this->getAppIdsCacheByOwner($owner);
    return $data ? $data->data : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAppFromCacheByName(string $owner, string $app_name): ?AppInterface {
    $app_ids_by_owner = $this->getAppIdsFromCacheByOwner($owner);
    if ($app_ids_by_owner === NULL || !array_key_exists($app_name, $app_ids_by_owner)) {
      return NULL;
    }
    return $this->getAppFromCacheByAppId($app_ids_by_owner[$app_name]);
  }

  /**
   * {@inheritdoc}
   */
  public function removeAppFromCache(AppInterface $app): void {
    $this->cacheBackend->delete($app->getAppId());
    // Invalidate everything tagged with this app.
    $this->cacheBackend->invalidateTags([$app->getAppId()]);
    $owner = $this->getAppOwner($app);
    // This is not strictly necessary because the app with the app id has
    // bean already removed, but let's try to keep everything clean, se we
    // also remove the related entry from the owner's app name => app id cache.
    $this->removeAppIdsFromCacheByOwner($owner, [$app->getName()]);
  }

  /**
   * {@inheritdoc}
   */
  public function removeAppIdsFromCacheByOwner(string $owner, array $app_names = []): void {
    $data = $this->getAppIdsCacheByOwner($owner);
    // There is nothing to do.
    if ($data === NULL) {
      return;
    }
    // Sanity check.
    if (!is_array($data->data)) {
      $data->data = [];
    }
    $remaining_app_names_by_owner = [];
    if (!empty($app_names)) {
      $app_names_as_keys = array_flip($app_names);
      $remaining_app_names_by_owner = array_diff_key($data->data, $app_names_as_keys);
      $app_ids_to_invalidate = array_intersect_key($data->data, $app_names_as_keys);
      // This is the most important task here, invalidate all app ids in cache
      // related to the provided app names.
      $this->cacheBackend->invalidateTags($app_ids_to_invalidate);
    }
    $this->cacheBackend->set($data->cid, $remaining_app_names_by_owner, Cache::PERMANENT, $data->tags);
  }

  /**
   * {@inheritdoc}
   */
  public function notAllAppsLoadedForOwner(string $owner): void {
    $this->cacheBackend->set($this->allAppsLoadedForOwnerCid($owner), FALSE, Cache::PERMANENT, [$this->appOwnerCacheId($owner)]);
  }

  /**
   * {@inheritdoc}
   */
  public function allAppsLoadedForOwner(string $owner): void {
    $this->cacheBackend->set($this->allAppsLoadedForOwnerCid($owner), TRUE, Cache::PERMANENT, [$this->appOwnerCacheId($owner)]);
  }

  /**
   * {@inheritdoc}
   */
  public function isAllAppsLoadedForOwner(string $owner): bool {
    $cache = $this->cacheBackend->get($this->allAppsLoadedForOwnerCid($owner));
    if ($cache) {
      return $cache->data;
    }

    return FALSE;
  }

  /**
   * Generates a unique cache id for the cache entry that stores all apps state.
   *
   * @param string $owner
   *   Id of the owner (developer id, email, company name).
   *
   * @return string
   *   Unique cache id.
   */
  private function allAppsLoadedForOwnerCid(string $owner): string {
    return $this->appOwnerCacheId($owner) . '-all-apps-loaded';
  }

  /**
   * Returns a unique cache id for the app owner.
   *
   * @param string $owner
   *   Id of an owner.
   *
   * @return string
   *   Unique cache id.
   *
   * @internal
   *   This method should not be overridden.
   */
  protected function appOwnerCacheId(string $owner): string {
    // Ensures app and developer uuids does not collide in cache.
    return 'owner-' . $owner;
  }

  /**
   * {@inheritdoc}
   */
  public function getAppOwner(AppInterface $app): string {
    if ($app instanceof DeveloperAppInterface) {
      return $app->getDeveloperId();
    }
    elseif ($app instanceof CompanyAppInterface) {
      return $app->getCompanyName();
    }

    throw new RuntimeException('Unable to identify app owner.');
  }

  /**
   * Returns the type of an app.
   *
   * @param \Apigee\Edge\Api\Management\Entity\AppInterface $app
   *   Developer- or company app.
   *
   * @return string
   *   The type of the app as a string, see constants.
   */
  protected function getAppType(AppInterface $app): string {
    if ($app instanceof DeveloperAppInterface) {
      return static::APP_TYPE_DEVELOPER;
    }
    elseif ($app instanceof CompanyAppInterface) {
      return static::APP_TYPE_COMPANY;
    }

    throw new RuntimeException('Unable to identify app owner.');
  }

  /**
   * Returns app ids cache from the cache backend related to the owner.
   *
   * @param string $owner
   *   Id of the owner (developer id, email, company name).
   *
   * @return null|object
   *   The cache item or NULL if it does not exist.
   */
  protected function getAppIdsCacheByOwner(string $owner) {
    $cache = $this->cacheBackend->get($this->appOwnerCacheId($owner));
    return $cache ? $cache : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(array $ids = []): void {
    if (empty($ids)) {
      $this->cacheBackend->deleteAll();
    }
    else {
      $this->cacheBackend->deleteMultiple($ids);
      // For extra safety.
      $this->cacheBackend->invalidateTags($ids);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resetCacheByAppType(string $type): void {
    if ($type === static::APP_TYPE_DEVELOPER) {
      $this->cacheBackend->invalidateTags([static::APP_TYPE_DEVELOPER]);
    }
    if ($type === static::APP_TYPE_COMPANY) {
      $this->cacheBackend->invalidateTags([static::APP_TYPE_COMPANY]);
    }
    else {
      throw new InvalidArgumentException("Unknown app type: {$type}.");
    }
  }

}
