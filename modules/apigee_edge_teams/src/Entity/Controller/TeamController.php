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

namespace Drupal\apigee_edge_teams\Entity\Controller;

use Apigee\Edge\Api\Management\Controller\CompanyController as EdgeCompanyController;
use Apigee\Edge\Api\Management\Controller\CompanyControllerInterface as EdgeCompanyControllerInterface;
use Apigee\Edge\Entity\EntityInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface;
use Drupal\apigee_edge\Entity\Controller\CachedAttributesAwareEntityControllerTrait;
use Drupal\apigee_edge\Entity\Controller\CachedEntityCrudOperationsControllerTrait;
use Drupal\apigee_edge\Entity\Controller\CachedPaginatedControllerHelperTrait;
use Drupal\apigee_edge\Entity\Controller\CachedPaginatedEntityIdListingControllerTrait;
use Drupal\apigee_edge\Entity\Controller\CachedPaginatedEntityListingControllerTrait;
use Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface;
use Drupal\apigee_edge\Entity\Controller\EntityCacheAwareControllerInterface;
use Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\apigee_edge_teams\CompanyMembershipObjectCacheInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Definition of the Team controller service.
 *
 * We call companies as teams in Drupal.
 */
final class TeamController implements TeamControllerInterface {

  use CachedEntityCrudOperationsControllerTrait {
    delete as private traitDelete;
  }
  use CachedPaginatedEntityIdListingControllerTrait;
  use CachedPaginatedEntityListingControllerTrait;
  use CachedPaginatedControllerHelperTrait;
  use CachedAttributesAwareEntityControllerTrait;

  /**
   * Local cache for the decorated company controller from the SDK.
   *
   * @var \Apigee\Edge\Api\Management\Controller\CompanyController|null
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
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  private $teamMembershipManager;

  /**
   * The developer controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface
   */
  private $developerController;

  /**
   * The developer entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $developerStorage;

  /**
   * The company membership object cache.
   *
   * @var \Drupal\apigee_edge_teams\CompanyMembershipObjectCacheInterface
   */
  private $companyMembershipObjectCache;

  /**
   * CompanyController constructor.
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface $developer_controller
   *   The developer controller service.
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Drupal\apigee_edge_teams\CompanyMembershipObjectCacheInterface $company_membership_object_cache
   *   The company membership object cache.
   */
  public function __construct(SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller, EntityCacheInterface $entity_cache, EntityIdCacheInterface $entity_id_cache, AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory, AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner, EntityTypeManagerInterface $entity_type_manager, DeveloperControllerInterface $developer_controller, TeamMembershipManagerInterface $team_membership_manager, CompanyMembershipObjectCacheInterface $company_membership_object_cache) {
    $this->connector = $connector;
    $this->orgController = $org_controller;
    $this->entityCache = $entity_cache;
    $this->entityIdCache = $entity_id_cache;
    $this->appCacheByOwnerFactory = $app_cache_by_owner_factory;
    $this->appNameCacheByOwnerFactory = $app_name_cache_by_owner;
    $this->developerStorage = $entity_type_manager->getStorage('developer');
    $this->developerController = $developer_controller;
    $this->teamMembershipManager = $team_membership_manager;
    $this->companyMembershipObjectCache = $company_membership_object_cache;
  }

  /**
   * Returns the decorated company controller from the SDK.
   *
   * @return \Apigee\Edge\Api\Management\Controller\CompanyControllerInterface
   *   The initialized company controller.
   */
  private function decorated(): EdgeCompanyControllerInterface {
    if ($this->instance === NULL) {
      $this->instance = new EdgeCompanyController($this->connector->getOrganization(), $this->connector->getClient(), NULL, $this->orgController);
    }
    return $this->instance;
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
   * {@inheritdoc}
   */
  public function setStatus(string $entityId, string $status): void {
    $this->decorated()->setStatus($entityId, $status);
    // Enforce reload of entity from Apigee Edge.
    $this->entityCache->removeEntities([$entityId]);
    $this->entityCache->allEntitiesInCache(FALSE);
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
  public function delete(string $entityId): EntityInterface {
    // Before we could delete this team let's collect all team members for
    // invalidating some related caches.
    $members = $this->teamMembershipManager->getMembers($entityId);

    /** @var \Apigee\Edge\Api\Management\Entity\CompanyInterface $entity */
    $entity = $this->traitDelete($entityId);

    if (!empty($members)) {
      // Invalidate developer storage's static cache to force reload
      // in \Drupal\apigee_edge\Entity\Developer::getCompanies().
      // (resetCache() does not invalidate the underlying controller's
      // static cache, see reasoning in method's body.)
      $this->developerStorage->resetCache($members);
      // Invalidate developer controller's cache to force reload in
      // \Drupal\apigee_edge\Entity\Developer::getCompanies().
      // If we would only invalidate the developer storage caches that would
      // not be enough within the same page request because developer storage
      // calls the developer controller that also has a static cache.
      if ($this->developerController instanceof EntityCacheAwareControllerInterface) {
        $this->developerController->entityCache()->removeEntities($members);
      }
    }

    // And of course, the company membership object cache has to be cleared as
    // well because we have warmed that up in the beginning of this method
    // by loading all members of this team.
    $this->companyMembershipObjectCache->removeMembership($entityId);

    // Invalidate app caches that belongs to this company.
    $app_cache = $this->appCacheByOwnerFactory->getAppCache($entity->id());
    $app_ids = [];
    /** @var \Apigee\Edge\Api\Management\Entity\CompanyAppInterface $app */
    foreach ($app_cache->getEntities() as $app) {
      $app_ids[] = $app->getAppId();
    }
    $app_cache->removeEntities($app_ids);
    // App cache has cleared all app names that it knows about
    // but it could happen that there are some remaining app names in the
    // app name cache that has not be created by app cache.
    $app_name_cache = $this->appNameCacheByOwnerFactory->getAppNameCache($entity->id());
    $app_name_cache->removeIds($app_name_cache->getIds());

    return $entity;
  }

}
