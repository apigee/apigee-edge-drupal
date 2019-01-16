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

namespace Drupal\apigee_edge_teams;

use Apigee\Edge\Api\Management\Structure\CompanyMembership;
use Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface;
use Drupal\apigee_edge\Entity\Controller\EntityCacheAwareControllerInterface;
use Drupal\apigee_edge\Entity\DeveloperCompaniesCacheInterface;
use Drupal\apigee_edge\Exception\DeveloperDoesNotExistException;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service that makes easier to work with company (team) memberships.
 *
 * It also handles cache invalidation.
 */
final class TeamMembershipManager implements TeamMembershipManagerInterface {

  /**
   * The developer entity storage.
   *
   * @var \Drupal\apigee_edge\Entity\Storage\DeveloperStorageInterface
   */
  private $developerStorage;

  /**
   * The company members controller factory service.
   *
   * @var \Drupal\apigee_edge_teams\CompanyMembersControllerFactoryInterface
   */
  private $companyMembersControllerFactory;

  /**
   * The developer companies cache.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperCompaniesCacheInterface
   */
  private $developerCompaniesCache;

  /**
   * The developer controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface
   */
  private $developerController;

  /**
   * The team entity storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamStorageInterface
   */
  private $teamStorage;

  /**
   * The team entity type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  private $teamEntityType;

  /**
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  private $cacheTagsInvalidator;

  /**
   * TeamMembershipManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\apigee_edge_teams\CompanyMembersControllerFactoryInterface $company_members_controller_factory
   *   The company members controller factory service.
   * @param \Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface $developer_controller
   *   The developer controller service.
   * @param \Drupal\apigee_edge\Entity\DeveloperCompaniesCacheInterface $developer_companies_cache
   *   The developer companies cache.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CompanyMembersControllerFactoryInterface $company_members_controller_factory, DeveloperControllerInterface $developer_controller, DeveloperCompaniesCacheInterface $developer_companies_cache, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->developerStorage = $entity_type_manager->getStorage('developer');
    $this->teamStorage = $entity_type_manager->getStorage('team');
    $this->teamEntityType = $entity_type_manager->getDefinition('team');
    $this->companyMembersControllerFactory = $company_members_controller_factory;
    $this->developerController = $developer_controller;
    $this->developerCompaniesCache = $developer_companies_cache;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public function getMembers(string $team): array {
    $controller = $this->companyMembersControllerFactory->companyMembersController($team);
    $members = $controller->getMembers();
    return array_keys($members->getMembers());
  }

  /**
   * {@inheritdoc}
   */
  public function addMembers(string $team, array $developers): void {
    $membership = new CompanyMembership(array_map(function ($item) {
      return NULL;
    }, array_flip($developers)));
    $controller = $this->companyMembersControllerFactory->companyMembersController($team);
    $controller->setMembers($membership);
    $this->invalidateCaches($team, $developers);
  }

  /**
   * {@inheritdoc}
   */
  public function removeMembers(string $team, array $developers): void {
    $controller = $this->companyMembersControllerFactory->companyMembersController($team);
    foreach ($developers as $developer) {
      $controller->removeMember($developer);
    }
    $this->invalidateCaches($team, $developers);
  }

  /**
   * {@inheritdoc}
   */
  public function getTeams(string $developer): array {
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $entity */
    $entity = $this->developerStorage->load($developer);
    if ($entity === NULL) {
      throw new DeveloperDoesNotExistException($developer);
    }
    // Developer entity's getCompanies() method should return the list of
    // companies where the developer is member.
    // @see \Drupal\apigee_edge\Entity\Developer::getCompanies()
    return $entity->getCompanies();
  }

  /**
   * Invalidates caches when membership state changes.
   *
   * @param string $team
   *   Name of a team.
   * @param array $developers
   *   Array of developer email addresses.
   */
  private function invalidateCaches(string $team, array $developers): void {
    // At this point we can assume that team entity exists because earlier
    // API calls have not fail.
    $team_entity = $this->teamStorage->load($team);
    // This invalidates every occupancies where $team has appeared.
    $tags = $team_entity->getCacheTags();
    // This invalidates cache on team listing page(s) because the list
    // of accessible teams for user(s) has changed.
    // (If we find this too much, later we can tag team listing pages with a
    // "team_list:{$developer_email}" tag and only invalidate those developers'
    // team listing pages whose membership state has changed.)
    $tags = array_merge($tags, $this->teamEntityType->getListCacheTags());
    $this->cacheTagsInvalidator->invalidateTags($tags);

    // Developer::getCompanies() must return the updated membership information.
    // @see \Drupal\apigee_edge\Entity\Developer::getCompanies()
    $developer_companies_cache_tags = array_map(function (string $developer) {
      return "developer:{$developer}";
    }, $developers);
    $this->developerCompaniesCache->invalidate($developer_companies_cache_tags);
    // Developer controller's cache has to be cleared as well otherwise
    // Developer::getCompanies() reloads the old data from the controller's
    // cache.
    if ($this->developerController instanceof EntityCacheAwareControllerInterface) {
      $this->developerController->entityCache()->removeEntities($developers);
    }
  }

}
