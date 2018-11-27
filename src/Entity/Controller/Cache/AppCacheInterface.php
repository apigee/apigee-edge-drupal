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

/**
 * Base definition of app cache used by app controllers in Drupal.
 *
 * @internal This interface may change before the first stable release.
 */
interface AppCacheInterface {

  public const APP_TYPE_DEVELOPER = 'developer_app';

  public const APP_TYPE_COMPANY = 'company_app';

  /**
   * Saves apps to cache.
   *
   * @param \Apigee\Edge\Api\Management\Entity\AppInterface[] $apps
   *   Array of developer- and/or company apps.
   */
  public function saveAppsToCache(array $apps): void;

  /**
   * Returns the app from the cache by its id (uuid).
   *
   * @param string $appId
   *   The app id.
   *
   * @return \Apigee\Edge\Api\Management\Entity\AppInterface|null
   *   The app if it has found, null otherwise.
   */
  public function getAppFromCacheByAppId(string $appId): ?AppInterface;

  /**
   * Removes an app from the cache.
   *
   * @param \Apigee\Edge\Api\Management\Entity\AppInterface $app
   *   Developer- or company app.
   */
  public function removeAppFromCache(AppInterface $app): void;

  /**
   * Get apps ids related related to the owner from the cache.
   *
   * @param string $owner
   *   Id of the owner (developer id, email, company name).
   *
   * @return array|null
   *   An associative array where keys are the app names and values are the
   *   app ids (uuid). It could happen that the returned app ids belongs to
   *   an expired/removed cache item (app).
   *   It returns null if cache entry has not found for this owner.
   */
  public function getAppIdsFromCacheByOwner(string $owner): ?array;

  /**
   * Returns an app from the cache by its name and owner.
   *
   * @param string $owner
   *   Id of the owner (developer id, email, company name).
   * @param string $app_name
   *   Name of the app.
   *
   * @return \Apigee\Edge\Api\Management\Entity\AppInterface|null
   *   The app if it has found, null otherwise.
   */
  public function getAppFromCacheByName(string $owner, string $app_name): ?AppInterface;

  /**
   * Saves app name => app id maps to owner's cache.
   *
   * @param string $owner
   *   Id of the owner (developer id, email, company name).
   * @param string[] $ids
   *   An associative array where keys are the app names and values are the
   *   app ids.
   */
  public function addAppIdsToCacheByOwner(string $owner, array $ids): void;

  /**
   * Removes app ids from the owner's app name => app id map.
   *
   * @param string $owner
   *   Id of the owner (developer id, email, company name).
   * @param string[] $app_names
   *   (Optional) Only remove app ids that belongs to the these app names.
   *   The default is to remove all app ids.
   */
  public function removeAppIdsFromCacheByOwner(string $owner, array $app_names = []): void;

  /**
   * Indicates that not all apps of the owner in cache.
   *
   * @param string $owner
   *   Id of the owner (developer id, email, company name).
   */
  public function notAllAppsLoadedForOwner(string $owner): void;

  /**
   * Indicates that all apps of the owner in (should be) cache.
   *
   * @param string $owner
   *   Id of the owner (developer id, email, company name).
   */
  public function allAppsLoadedForOwner(string $owner): void;

  /**
   * Returns whether all apps of the owner in cache.
   *
   * Which usually means there is no need to call Apigee Edge.
   *
   * @param string $owner
   *   Id of the owner (developer id, email, company name).
   *
   * @return bool
   *   TRUE if all apps of the owner (should be) in the cache, FALSE otherwise.
   */
  public function isAllAppsLoadedForOwner(string $owner): bool;

  /**
   * Returns the owner (id) of an app.
   *
   * @param \Apigee\Edge\Api\Management\Entity\AppInterface $app
   *   Developer- or company app.
   *
   * @return string
   *   Developer id (uuid) or company name.
   */
  public function getAppOwner(AppInterface $app): string;

  /**
   * Resets app caches.
   *
   * @param array $ids
   *   App ids to be invalidated, if empty all gets invalidated.
   */
  public function resetCache(array $ids = []): void;

  /**
   * Invalidates app cache entries by app type.
   *
   * @param string $type
   *   App type. See constants.
   */
  public function resetCacheByAppType(string $type): void;

}
