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
use Apigee\Edge\Api\Management\Entity\AppInterface;
use Apigee\Edge\Structure\PagerInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface;
use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Definition of the App controller service.
 *
 * This integrates the Management API's App controller from the SDK's with
 * Drupal. It uses a shared (not internal) app cache to reduce the number of
 * API calls that we send to Apigee Edge.
 *
 * TODO Leverage cache in those methods that works with app ids not app object.
 */
final class AppController extends AppControllerBase implements AppControllerInterface {

  /**
   * The decorated app controller from the SDK.
   *
   * @var \Apigee\Edge\Api\Management\Controller\AppController
   */
  private $decorated;

  /**
   * AppController constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache
   *   The app cache.
   */
  public function __construct(SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller, AppCacheInterface $app_cache) {
    parent::__construct($connector, $org_controller, $app_cache);
    $this->decorated = new EdgeAppController($connector->getOrganization(), $connector->getClient(), NULL, $org_controller);
  }

  /**
   * {@inheritdoc}
   */
  public function loadApp(string $appId): AppInterface {
    $app = $this->appCache->getAppFromCacheByAppId($appId);
    if ($app === NULL) {
      $app = $this->decorated->loadApp($appId);
      $this->appCache->saveAppsToCache([$app]);
    }
    return $app;
  }

  /**
   * {@inheritdoc}
   */
  public function listAppIds(PagerInterface $pager = NULL): array {
    return $this->decorated->listAppIds($pager);
  }

  /**
   * {@inheritdoc}
   */
  public function listApps(bool $includeCredentials = FALSE, PagerInterface $pager = NULL): array {
    $apps = $this->decorated->listApps($includeCredentials, $pager);
    // We only cache "complete" apps, we do not cache incomplete apps.
    if ($includeCredentials) {
      $this->appCache->saveAppsToCache($apps);
      // Null pager means the PHP API client has loaded all apps from
      // Apigee Edge.
      if ($pager === NULL) {
        $owners = [];
        foreach ($apps as $app) {
          $owners[] = $this->appCache->getAppOwner($app);
        }
        foreach (array_unique($owners) as $owner) {
          $this->appCache->allAppsLoadedForOwner($owner);
        }
      }
    }

    return $apps;
  }

  /**
   * {@inheritdoc}
   */
  public function listAppIdsByStatus(string $status, PagerInterface $pager = NULL): array {
    return $this->decorated->listAppIdsByStatus($status, $pager);
  }

  /**
   * {@inheritdoc}
   */
  public function listAppsByStatus(string $status, bool $includeCredentials = TRUE, PagerInterface $pager = NULL): array {
    $apps = $this->decorated->listAppsByStatus($status, $includeCredentials, $pager);
    // Nice to have, after we have added cache support for methods that return
    // app ids then we can compare the list of apps cached here
    // and the already cached app ids per owner to call allAppsLoadedForOwner()
    // if we have cached all apps of a developer/company here.
    $this->appCache->saveAppsToCache($apps);

    return $apps;
  }

  /**
   * {@inheritdoc}
   */
  public function listAppIdsByType(string $appType, PagerInterface $pager = NULL): array {
    return $this->decorated->listAppIdsByType($appType, $pager);
  }

  /**
   * {@inheritdoc}
   */
  public function listAppIdsByFamily(string $appFamily, PagerInterface $pager = NULL): array {
    return $this->decorated->listAppIdsByFamily($appFamily, $pager);
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganisationName(): string {
    return $this->decorated->getOrganisationName();
  }

  /**
   * {@inheritdoc}
   */
  public function createPager(int $limit = 0, ?string $startKey = NULL): PagerInterface {
    return $this->decorated->createPager($limit, $startKey);
  }

}
