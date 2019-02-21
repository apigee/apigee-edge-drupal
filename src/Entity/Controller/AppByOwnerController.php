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
use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface;
use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Base class for developer- and company app controller services in Drupal.
 */
abstract class AppByOwnerController extends AppControllerBase implements AppByOwnerControllerInterface {

  use CachedAttributesAwareEntityControllerTrait;
  use CachedEntityCrudOperationsControllerTrait;
  use CachedPaginatedEntityIdListingControllerTrait;
  use CachedPaginatedControllerHelperTrait;

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
   * App owner's dedicated app cache that uses app names as cache ids.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface
   */
  protected $appCacheByOwner;

  /**
   * App owner's dedicated app name cache.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface
   */
  protected $appNameCacheByOwner;

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
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory
   *   The app cache by owner factory service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner_factory
   *   The app name cache by owner factory service.
   */
  public function __construct(string $owner, SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller, AppCacheInterface $app_cache, AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory, AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner_factory) {
    parent::__construct($connector, $org_controller, $app_cache);
    $this->owner = $owner;
    $this->appCacheByOwner = $app_cache_by_owner_factory->getAppCache($owner);
    $this->appNameCacheByOwner = $app_name_cache_by_owner_factory->getAppNameCache($owner);
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
  public function entityCache(): EntityCacheInterface {
    return $this->appCacheByOwner;
  }

  /**
   * {@inheritdoc}
   */
  protected function entityIdCache(): EntityIdCacheInterface {
    return $this->appNameCacheByOwner;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(): array {
    if ($this->entityCache()->isAllEntitiesInCache()) {
      return $this->entityCache()->getEntities();
    }

    $entities = $this->decorated()->getEntities();
    $this->entityCache()->saveEntities($entities);
    $this->entityCache()->allEntitiesInCache(TRUE);

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entity_id): EntityInterface {
    // Check whether the $entityId is an app name and it can
    // be found in app owner's cache.
    $entity = $this->appCacheByOwner->getEntity($entity_id);
    // So is it an app id (UUID) then?
    if ($entity === NULL) {
      $entity = $this->appCache->getEntity($entity_id);
    }
    // The app has not found in caches so we have to load it from Apigee
    // Edge.
    if ($entity === NULL) {
      $entity = $this->decorated()->load($entity_id);
      // Saving it to app owner's cache ensures that app cache and app id
      // cache gets updated as well.
      $this->appCacheByOwner->saveEntities([$entity]);
    }

    return $entity;
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
  public function setStatus(string $entity_id, string $status): void {
    $this->decorated()->setStatus($entity_id, $status);
    // The status of the app has changed so we have to remove it from the
    // cache and enforce its reload from Apigee Edge.
    // Here entity id can be only the name of the app and not its UUID.
    // @see https://apidocs.apigee.com/management/apis/post/organizations/%7Borg_name%7D/developers/%7Bdeveloper_email_or_id%7D/apps/%7Bapp_name%7D
    $this->appCacheByOwner->removeEntities([$entity_id]);
  }

}
