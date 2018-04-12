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

namespace Drupal\apigee_edge\Entity\Controller;

use Apigee\Edge\Api\Management\Controller\AppController;
use Apigee\Edge\Api\Management\Controller\DeveloperAppController as EdgeDeveloperAppController;
use Apigee\Edge\Api\Management\Controller\DeveloperAppControllerInterface as EdgeDeveloperAppControllerInterface;
use Apigee\Edge\Api\Management\Entity\AppInterface;
use Apigee\Edge\Api\Management\Entity\DeveloperApp as EdgeDeveloperApp;
use Apigee\Edge\Api\Management\Entity\DeveloperAppInterface as EdgeDeveloperAppInterface;
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Apigee\Edge\Structure\CpsListLimitInterface;
use Drupal\apigee_edge\Entity\AppCredentialStorageAwareTrait;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\apigee_edge\Entity\EntityConvertAwareTrait;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;

/**
 * Advanced version of Apigee Edge SDK's developer app controller.
 *
 * It combines the bests of the DeveloperAppController and AppController
 * classes and also provides additional features that the SDK's built in
 * classes.
 *
 * We intentionally did not override the getEntityClass() here to get back
 * Drupal developer app entities from SDK controllers. If we would do that
 * then calling $app->getCredentials() here on a Drupal developer app would
 * cause infinite loop.
 *
 * @see \Drupal\apigee_edge\Entity\DeveloperApp::getCredentials()
 *
 * EntityConvertAwareTrait can not be used in the same time with
 * DrupalEntityControllerAwareTrait, because even if we try to alias the
 * first one's convertToDrupalEntity as conflict resolution it in never become
 * compatible with DrupalEntityControllerInterface::convertToSdkEntity.
 * (PHP bug?)
 */
class DeveloperAppController extends AppController implements DeveloperAppControllerInterface {

  use AppCredentialStorageAwareTrait;
  use DrupalEntityControllerAwareTrait {
    loadMultiple as private traitLoadMultiple;
  }

  /**
   * Static cache for already loaded developer apps by developer app id.
   *
   * This is necessary to reduce API calls on the My apps page.
   *
   * @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface[]
   */
  private static $cacheByAppId = [];

  /**
   * Static cache for already loaded developer apps by developerId and app name.
   *
   * This is necessary to reduce API calls on the My apps page.
   *
   * @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface[]
   */
  private static $cacheByDeveloperIdAppName = [];

  /**
   * {@inheritdoc}
   *
   * We had to override this because in this special case
   * parent::getEntityClass() returns an empty string.
   *
   * @see AppController::getEntityClass()
   */
  public function convertToSdkEntity(EntityInterface $drupal_entity): EdgeEntityInterface {
    return EntityConvertAwareTrait::convertToSdkEntity($drupal_entity, EdgeDeveloperApp::class);
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entityId): EdgeEntityInterface {
    if (isset(static::$cacheByAppId[$entityId])) {
      $app = static::$cacheByAppId[$entityId];
    }
    else {
      /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface $app */
      $app = $this->loadApp($entityId);
      $this->saveEntityToStaticCaches($app);
    }
    return EntityConvertAwareTrait::convertToDrupalEntity($app, DeveloperApp::class);
  }

  /**
   * Creates a developer app controller.
   *
   * @param string $developerId
   *   UUID or email address of a developer.
   *
   * @return \Apigee\Edge\Api\Management\Controller\DeveloperAppControllerInterface
   *   Developer app controller from the SDK.
   */
  protected function createDeveloperAppController(string $developerId): EdgeDeveloperAppControllerInterface {
    return new EdgeDeveloperAppController($this->getOrganisation(), $developerId, $this->client);
  }

  /**
   * {@inheritdoc}
   */
  public function create(EdgeEntityInterface $entity): void {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $controller = $this->createDeveloperAppController($entity->getDeveloperId());
    $controller->create($entity);
    $this->saveEntityToStaticCaches($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function update(EdgeEntityInterface $entity): void {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $controller = $this->createDeveloperAppController($entity->getDeveloperId());
    $controller->update($entity);
    $this->saveEntityToStaticCaches($entity);
  }

  /**
   * {@inheritdoc}
   *
   * App credential storage entries invalidated in the DeveloperAppStorage.
   *
   * @see \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorage::doDelete()
   */
  public function delete(string $entityId): EdgeEntityInterface {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $entity = $this->loadApp($entityId);
    $controller = $this->createDeveloperAppController($entity->getDeveloperId());
    $return = $controller->delete($entity->getName());
    unset(static::$cacheByAppId[$entity->getAppId()]);
    unset(static::$cacheByAppId[$entity->getDeveloperId()][$entity->getName()]);
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(CpsListLimitInterface $cpsLimit = NULL): array {
    $developerAppIds = $this->getEntityIds($cpsLimit);
    // Do not care about what is in the static cache, we have to load all
    // developer app entities anyway.
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperApp[] $allApps */
    $allApps = $this->listApps(TRUE, $cpsLimit);
    $this->saveEntitiesToStaticCache($allApps);
    $apps = array_intersect_key($allApps, array_flip($developerAppIds));
    $converted = array_map(function (EdgeDeveloperApp $app) {
      return EntityConvertAwareTrait::convertToDrupalEntity($app, DeveloperApp::class);
    }, $apps);
    return $converted;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIds(CpsListLimitInterface $cpsLimit = NULL): array {
    return $this->listAppIdsByType('developer', $cpsLimit);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByAppName(string $developerId, string $appName) : EdgeEntityInterface {
    if (isset(static::$cacheByDeveloperIdAppName[$developerId][$appName])) {
      $app = static::$cacheByDeveloperIdAppName[$developerId][$appName];
    }
    else {
      $controller = $this->createDeveloperAppController($developerId);
      /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface $app */
      $app = $controller->load($appName);
      $this->saveEntityToStaticCaches($app);
    }
    return EntityConvertAwareTrait::convertToDrupalEntity($app, DeveloperApp::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntitiesByDeveloper(string $developerId): array {
    // This only works if the passed developer id is actually the developer id
    // (uuid) of a developer and not the email address of it.
    // Adding an additional cache layer that would use developer email
    // + appName/appId as an index could introduce new problems, because for
    // example in the delete() method we would need to load the developer
    // entity to get the email address of the app owner and invalidate related
    // entries in that cache too.
    if (isset(static::$cacheByDeveloperIdAppName[$developerId])) {
      $apps = static::$cacheByDeveloperIdAppName[$developerId];
    }
    else {
      /** @var \Apigee\Edge\Api\Management\Controller\DeveloperAppControllerInterface $controller */
      $controller = $this->createDeveloperAppController($developerId);
      $apps = $controller->getEntities();
      $this->saveEntitiesToStaticCache($apps);
    }
    // Store loaded credential's in user's private credential store to
    // reduce number of API calls.
    // @see \Drupal\apigee_edge\Entity\DeveloperApp::getCredentials()
    $converted = array_map(function (EdgeDeveloperApp $app) {
      return EntityConvertAwareTrait::convertToDrupalEntity($app, DeveloperApp::class);
    }, $apps);
    return $converted;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIdsByDeveloper(string $developerId): array {
    /** @var \Apigee\Edge\Api\Management\Controller\DeveloperAppControllerInterface $controller */
    $controller = $this->createDeveloperAppController($developerId);
    return $controller->getEntityIds();
  }

  /**
   * Saves a developer app entity to caches.
   *
   * @param \Apigee\Edge\Api\Management\Entity\AppInterface $app
   *   App entity.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   If saving credentials to the intermediate credential storage fails.
   */
  private function saveEntityToStaticCaches(AppInterface $app) {
    // Do not try to save non developer apps (ex.: company apps) to this cache.
    if (!in_array(EdgeDeveloperAppInterface::class, class_implements($app))) {
      return;
    }
    static::$cacheByAppId[$app->getAppId()] = $app;
    NestedArray::setValue(static::$cacheByDeveloperIdAppName, [$app->getDeveloperId(), $app->getName()], $app);
    // Store loaded credential's in user's private credential store to
    // reduce number of API calls.
    // @see \Drupal\apigee_edge\Entity\DeveloperApp::getCredentials()
    // Do this here too because that is the easiest.
    $this->saveAppCredentialsToStorage($app->getDeveloperId(), $app->getName(), $app->getCredentials());
  }

  /**
   * Saves app entities to caches.
   *
   * @param \Apigee\Edge\Api\Management\Entity\AppInterface[] $apps
   *   Array of app entities.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *   If saving credentials to the intermediate credential storage fails.
   */
  private function saveEntitiesToStaticCache(array $apps) {
    foreach ($apps as $app) {
      $this->saveEntityToStaticCaches($app);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL): array {
    $apps = [];
    if ($ids !== NULL) {
      if (count($ids) === 1) {
        $entity = $this->load(reset($ids));
        return [$entity->id() => $entity];
      }
      else {
        $apps = [];
        foreach ($ids as $key => $id) {
          if (isset(static::$cacheByAppId[$id])) {
            $apps[$id] = static::$cacheByAppId[$id];
            unset($ids[$key]);
          }
        }
      }
    }

    // If we  have all entities in cache then were fine there is no need
    // to get all entities from Apigee Edge to be able to filter that
    // by ids.
    if ($ids === NULL || !empty($ids)) {
      $apps = $this->traitLoadMultiple($ids);
      $this->saveEntitiesToStaticCache($apps);
    }

    $converted = array_map(function (EdgeDeveloperApp $app) {
      return EntityConvertAwareTrait::convertToDrupalEntity($app, DeveloperApp::class);
    }, $apps);
    return $converted;
  }

  /**
   * {@inheritdoc}
   */
  public function loadApp(string $appId): AppInterface {
    if (isset(static::$cacheByAppId[$appId])) {
      $app = static::$cacheByAppId[$appId];
    }
    else {
      /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface $app */
      $app = parent::loadApp($appId);
      $this->saveEntityToStaticCaches($app);
    }
    return $app;
  }

  /**
   * {@inheritdoc}
   */
  public function listApps(bool $includeCredentials = TRUE, CpsListLimitInterface $cpsLimit = NULL): array {
    $apps = parent::listApps($includeCredentials, $cpsLimit);
    $this->saveEntitiesToStaticCache($apps);
    return $apps;
  }

  /**
   * {@inheritdoc}
   */
  public function listAppsByStatus(string $status, bool $includeCredentials = TRUE, CpsListLimitInterface $cpsLimit = NULL): array {
    $apps = parent::listAppsByStatus($status, $includeCredentials, $cpsLimit);
    $this->saveEntitiesToStaticCache($apps);
    return $apps;
  }

}
