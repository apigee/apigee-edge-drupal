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

use Apigee\Edge\Api\Management\Controller\AppByOwnerControllerInterface;
use Apigee\Edge\Api\Management\Controller\AppByOwnerControllerInterface as EdgeAppByOwnerControllerInterface;
use Apigee\Edge\Entity\EntityInterface;
use Apigee\Edge\Structure\AttributesProperty;
use Apigee\Edge\Structure\PagerInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface;
use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Base class for developer- and company app controller services in Drupal.
 */
abstract class AppByOwnerController extends AppControllerBase implements AppByOwnerControllerInterface {

  /**
   * Local cache for app by owner controller instances.
   *
   * @var \Apigee\Edge\Api\Management\Controller\AppByOwnerControllerInterface[]
   *   Instances of app by owner controllers.
   */
  protected $instances = [];

  /**
   * The owner of an app.
   *
   * It could be a developer's email address, uuid or a company's company name.
   *
   * @var string
   */
  protected $owner;

  /**
   * AppByOwnerController constructor.
   *
   * @param string $owner
   *   A developer's email address, uuid or a company's company name.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache
   *   The app cache.
   */
  public function __construct(string $owner, SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller, AppCacheInterface $app_cache) {
    parent::__construct($connector, $org_controller, $app_cache);
    $this->owner = $owner;
  }

  /**
   * Returns the decorated developer- or company app controller.
   *
   * @return \Apigee\Edge\Api\Management\Controller\AppByOwnerControllerInterface
   *   The initialized developer- or company app controller.
   */
  abstract protected function decorated() : EdgeAppByOwnerControllerInterface;

  /**
   * {@inheritdoc}
   */
  public function getEntities(): array {
    if ($this->appCache->isAllAppsLoadedForOwner($this->owner)) {
      // This should not return null if the above condition is true.
      return $this->appCache->getAppIdsFromCacheByOwner($this->owner);
    }
    else {
      /** @var \Apigee\Edge\Api\Management\Entity\AppInterface[] $apps */
      $apps = $this->decorated()->getEntities();
      // Save all apps to cache and mark that we have loaded all apps of
      // this particular owner.
      $this->appCache->saveAppsToCache($apps);
      $this->appCache->allAppsLoadedForOwner($this->owner);
    }

    return $apps;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes(string $entityId): AttributesProperty {
    return $this->decorated()->getAttributes($entityId);
  }

  /**
   * {@inheritdoc}
   */
  public function getAttribute(string $entityId, string $name): string {
    return $this->decorated()->getAttribute($entityId, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttributes(string $entityId, AttributesProperty $attributes): AttributesProperty {
    return $this->decorated()->updateAttributes($entityId, $attributes);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttribute(string $entityId, string $name, string $value): string {
    return $this->decorated()->updateAttribute($entityId, $name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAttribute(string $entityId, string $name): void {
    $this->decorated()->deleteAttribute($entityId, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function create(EntityInterface $entity): void {
    /** @var \Apigee\Edge\Api\Management\Entity\AppInterface $entity */
    $this->decorated()->create($entity);
    // No need to change the state of isAllAppsLoadedForOwner().
    $this->appCache->saveAppsToCache([$entity]);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $entityId): EntityInterface {
    /** @var \Apigee\Edge\Api\Management\Entity\AppInterface $app */
    $app = $this->decorated()->delete($entityId);
    // No need to change the state of isAllAppsLoadedForOwner().
    $this->appCache->removeAppFromCache($app);
    return $app;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entityId): EntityInterface {
    // Check whether the entity id in this context is an app name and it can
    // be found in cache.
    $app = $this->appCache->getAppFromCacheByName($this->owner, $entityId);
    // So is it an app id (UUID) then?
    if ($app === NULL) {
      $app = $this->appCache->getAppFromCacheByAppId($entityId);
    }
    // The app has not found in the cache so we have to load it from Apigee
    // Edge.
    if ($app === NULL) {
      /** @var \Apigee\Edge\Api\Management\Entity\AppInterface $app */
      $app = $this->decorated()->load($entityId);
      // No need to change the state of isAllAppsLoadedForOwner().
      $this->appCache->saveAppsToCache([$app]);
    }
    return $app;
  }

  /**
   * {@inheritdoc}
   */
  public function update(EntityInterface $entity): void {
    /** @var \Apigee\Edge\Api\Management\Entity\AppInterface $entity */
    $this->decorated()->update($entity);
    // No need to change the state of isAllAppsLoadedForOwner().
    $this->appCache->saveAppsToCache([$entity]);
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganisationName(): string {
    return $this->decorated()->getOrganisationName();
  }

  /**
   * {@inheritdoc}
   */
  public function createPager(int $limit = 0, ?string $startKey = NULL): PagerInterface {
    return $this->decorated()->createPager($limit, $startKey);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIds(PagerInterface $pager = NULL): array {
    return $this->decorated()->getEntityIds($pager);
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $entityId, string $status): void {
    $this->decorated()->setStatus($entityId, $status);
    // The status of the app has changed so we have to remove it from the
    // cache and enforce its reload from Apigee Edge.
    // Here entity id can be only the name of the app and not its UUID.
    // @see https://apidocs.apigee.com/management/apis/post/organizations/%7Borg_name%7D/developers/%7Bdeveloper_email_or_id%7D/apps/%7Bapp_name%7D
    $app_from_cache = $this->appCache->getAppFromCacheByName($this->owner, $entityId);
    if ($app_from_cache) {
      $this->appCache->removeAppFromCache($app_from_cache);
      // We have to mark cache as incomplete to enforce reload where it is
      // needed, ex.: getEntities().
      $this->appCache->notAllAppsLoadedForOwner($this->owner);
    }
  }

}
