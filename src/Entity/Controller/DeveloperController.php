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

use Apigee\Edge\Api\Management\Controller\DeveloperController as EdgeDeveloperController;
use Apigee\Edge\Api\Management\Controller\DeveloperControllerInterface as EdgeDeveloperControllerInterface;
use Apigee\Edge\Api\Management\Entity\DeveloperInterface;
use Apigee\Edge\Entity\EntityInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface;
use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Definition of the Developer controller service.
 *
 * This integrates the Management API's Developer controller from the
 * SDK's with Drupal.
 */
final class DeveloperController implements DeveloperControllerInterface, EntityCacheAwareControllerInterface {

  use CachedEntityCrudOperationsControllerTrait {
    delete as private traitDelete;
  }
  use CachedPaginatedEntityIdListingControllerTrait;
  use CachedPaginatedEntityListingControllerTrait;
  use CachedPaginatedControllerHelperTrait;
  use CachedAttributesAwareEntityControllerTrait;

  /**
   * Local cache for the decorated developer controller from the SDK.
   *
   * @var \Apigee\Edge\Api\Management\Controller\DeveloperController|null
   *
   * @see decorated()
   */
  private $instance;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $connector;

  /**
   * The organization controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface
   */
  private $orgController;

  /**
   * The entity cache.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface
   */
  private $entityCache;

  /**
   * The entity id cache.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface
   */
  private $entityIdCache;

  /**
   * The app cache by owner factory service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface
   */
  private $appCacheByOwnerFactory;

  /**
   * The app name cache by owner factory service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface
   */
  private $appNameCacheByOwnerFactory;

  /**
   * DeveloperController constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface $entity_cache
   *   The entity cache used by this controller.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface $entity_id_cache
   *   The entity id cache used by this controller.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory
   *   The app cache by owner factory service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner
   *   The app name cache by owner factory service.
   */
  public function __construct(SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller, EntityCacheInterface $entity_cache, EntityIdCacheInterface $entity_id_cache, AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory, AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner) {
    $this->connector = $connector;
    $this->orgController = $org_controller;
    $this->entityCache = $entity_cache;
    $this->entityIdCache = $entity_id_cache;
    $this->appCacheByOwnerFactory = $app_cache_by_owner_factory;
    $this->appNameCacheByOwnerFactory = $app_name_cache_by_owner;
  }

  /**
   * {@inheritdoc}
   */
  public function entityCache(): EntityCacheInterface {
    return $this->entityCache;
  }

  /**
   * {@inheritdoc}
   */
  protected function entityIdCache(): EntityIdCacheInterface {
    return $this->entityIdCache;
  }

  /**
   * Returns the decorated developer controller from the SDK.
   *
   * @return \Apigee\Edge\Api\Management\Controller\DeveloperControllerInterface
   *   The initialized developer controller.
   */
  protected function decorated(): EdgeDeveloperControllerInterface {
    if ($this->instance === NULL) {
      $this->instance = new EdgeDeveloperController($this->connector->getOrganization(), $this->connector->getClient(), NULL, $this->orgController);
    }
    return $this->instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeveloperByApp(string $app_name): DeveloperInterface {
    $developer = $this->decorated()->getDeveloperByApp($app_name);
    // We do not keep cache entries about developer and app relationships so
    // we could not serve this request from cache but at least we add the
    // loaded developer to the cache here.
    $this->entityCache->saveEntities([$developer]);
    return $developer;
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
    // Enforce reload of entity from Apigee Edge.
    $this->entityCache->removeEntities([$entity_id]);
    $this->entityCache->allEntitiesInCache(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $entity_id): EntityInterface {
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperInterface $entity */
    $entity = $this->traitDelete($entity_id);
    // Invalidate app caches that belongs to this developer.
    // This is implementation probably overcomplicated,
    // we may optimize this later.
    foreach ([$entity->getEmail(), $entity->getDeveloperId()] as $owner) {
      $app_cache = $this->appCacheByOwnerFactory->getAppCache($owner);
      $app_names = [];
      /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface $app */
      foreach ($app_cache->getEntities() as $app) {
        $app_names[] = $app->getAppId();
      }
      $app_cache->removeEntities($app_names);
      // App cache has cleared all app names that it knows about
      // but it could happen that there are some remaining app names in the
      // app name cache that has not be created by app cache.
      $app_name_cache = $this->appNameCacheByOwnerFactory->getAppNameCache($owner);
      $app_name_cache->removeIds($app_name_cache->getIds());
    }
    return $entity;
  }

}
