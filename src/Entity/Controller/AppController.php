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

namespace Drupal\apigee_edge\Entity\Controller;

use Apigee\Edge\Api\Management\Controller\AppController as EdgeAppController;
use Apigee\Edge\Api\Management\Controller\AppControllerInterface as EdgeAppControllerInterface;
use Apigee\Edge\Api\Management\Entity\AppInterface;
use Apigee\Edge\Structure\PagerInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppIdCache;
use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Definition of the App controller service.
 *
 * This integrates the Management API's App controller from the SDK's with
 * Drupal. It uses a shared (not internal) app cache to reduce the number of
 * API calls that we send to Apigee Edge.
 */
final class AppController extends AppControllerBase implements AppControllerInterface {

  use CachedPaginatedControllerHelperTrait;

  /**
   * Local cache for the decorated app controller from the SDK.
   *
   * @var \Apigee\Edge\Api\Management\Controller\AppController|null
   *
   * @see decorated()
   */
  private $instance;

  /**
   * The (general) app by owner cache factory service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface
   */
  private $appByOwnerAppCacheFactory;

  /**
   * The app id cache service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface
   */
  private $appIdCache;

  /**
   * AppController constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache
   *   The app cache that stores apps by their ids (UUIDs).
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppIdCache $app_id_cache
   *   The app id cache that stores app UUIDs.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory
   *   The (general) app cache by owner factory service.
   */
  public function __construct(SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller, AppCacheInterface $app_cache, AppIdCache $app_id_cache, AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory) {
    parent::__construct($connector, $org_controller, $app_cache);
    $this->appByOwnerAppCacheFactory = $app_cache_by_owner_factory;
    $this->appIdCache = $app_id_cache;
  }

  /**
   * Returns the decorated app controller from the SDK.
   *
   * @return \Apigee\Edge\Api\Management\Controller\AppControllerInterface
   *   The initialized app controller.
   */
  protected function decorated(): EdgeAppControllerInterface {
    if ($this->instance === NULL) {
      $this->instance = new EdgeAppController($this->connector->getOrganization(), $this->connector->getClient(), NULL, $this->organizationController);
    }
    return $this->instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadApp(string $app_id): AppInterface {
    $app = $this->appCache->getEntity($app_id);
    if ($app === NULL) {
      $app = $this->decorated()->loadApp($app_id);
      $this->appCache->saveEntities([$app]);
    }
    return $app;
  }

  /**
   * {@inheritdoc}
   */
  public function listAppIds(PagerInterface $pager = NULL): array {
    if ($this->appIdCache->isAllIdsInCache()) {
      if ($pager === NULL) {
        return $this->appIdCache->getIds();
      }
      else {
        return $this->extractSubsetOfAssociativeArray($this->appIdCache->getIds(), $pager->getLimit(), $pager->getStartKey());
      }
    }

    $ids = $this->decorated()->listAppIds($pager);
    $this->appIdCache->saveIds($ids);
    $this->appIdCache->allIdsInCache(TRUE);
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function listApps(bool $include_credentials = FALSE, PagerInterface $pager = NULL): array {
    // If all entities in the cache and apps with credentials should be
    // returned.
    if ($this->appCache->isAllEntitiesInCache() && $include_credentials === TRUE) {
      if ($pager === NULL) {
        return $this->appCache->getEntities();
      }
      else {
        return $this->extractSubsetOfAssociativeArray($this->appCache->getEntities(), $pager->getLimit(), $pager->getStartKey());
      }
    }

    $result = $this->decorated()->listApps($include_credentials, $pager);
    // We only cache "complete" apps, we do not cache apps without credentials.
    if ($include_credentials) {
      $this->appCache->saveEntities($result);
      // Null pager means the PHP API client has loaded all apps from
      // Apigee Edge.
      if ($pager === NULL) {
        $this->appCache->allEntitiesInCache(TRUE);
        $apps_by_owner = [];
        foreach ($result as $app) {
          $apps_by_owner[$this->appCache->getAppOwner($app)][$app->getAppId()] = $app;
        }
        foreach ($apps_by_owner as $owner => $apps) {
          $apps_by_owner_cache = $this->appByOwnerAppCacheFactory->getAppCache($owner);
          $apps_by_owner_cache->saveEntities($apps);
          $apps_by_owner_cache->allEntitiesInCache(TRUE);
        }
      }
    }
    else {
      // Little trick here, even if we have not loaded complete app objects
      // we can still cache their ids.
      $this->appIdCache->saveEntities($result);
      // Moreover we can mark the app id cache as completed if pager is null.
      if ($pager === NULL) {
        $this->appIdCache->allIdsInCache(TRUE);
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function listAppIdsByStatus(string $status, PagerInterface $pager = NULL): array {
    $apps_from_cache = $this->getAppsFromCacheByStatus($status, $pager);
    if ($apps_from_cache !== NULL) {
      return array_map(function (AppInterface $app) {
        return $app->id();
      }, $apps_from_cache);
    }

    return $this->decorated()->listAppIdsByStatus($status, $pager);
  }

  /**
   * {@inheritdoc}
   */
  public function listAppsByStatus(string $status, bool $include_credentials = TRUE, PagerInterface $pager = NULL): array {
    $apps_from_cache = $this->getAppsFromCacheByStatus($status, $pager);
    if ($apps_from_cache !== NULL) {
      return $apps_from_cache;
    }

    $apps = $this->decorated()->listAppsByStatus($status, $include_credentials, $pager);
    // Nice to have, after we have added cache support for methods that return
    // app ids then we can compare the list of returned apps here
    // and the already cached app ids per owner to call saveEntities()
    // if we have cached all apps of a developer/company here.
    $this->appCache->saveEntities($apps);

    return $apps;
  }

  /**
   * Returns apps from the cache by status.
   *
   * @param string $status
   *   App status.
   * @param \Apigee\Edge\Structure\PagerInterface|null $pager
   *   Pager.
   *
   * @return array|null
   *   If not all apps in the cache it returns null, otherwise it returns the
   *   required amount of apps from the cache.
   */
  private function getAppsFromCacheByStatus(string $status, PagerInterface $pager = NULL): ?array {
    $apps = NULL;
    if ($this->appCache->isAllEntitiesInCache()) {
      if ($pager === NULL) {
        $apps = $this->appCache->getEntities();
      }
      else {
        $apps = $this->extractSubsetOfAssociativeArray($this->appCache->getEntities(), $pager->getLimit(), $pager->getStartKey());
      }

      $apps = array_filter($apps, function (AppInterface $app) use ($status) {
        return $app->getStatus() === $status;
      });
    }

    return $apps;
  }

  /**
   * {@inheritdoc}
   */
  public function listAppIdsByType(string $app_type, PagerInterface $pager = NULL): array {
    return $this->decorated()->listAppIdsByType($app_type, $pager);
  }

  /**
   * {@inheritdoc}
   */
  public function listAppIdsByFamily(string $app_family, PagerInterface $pager = NULL): array {
    return $this->decorated()->listAppIdsByFamily($app_family, $pager);
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganisationName(): string {
    return $this->decorated()->getOrganisationName();
  }

}
