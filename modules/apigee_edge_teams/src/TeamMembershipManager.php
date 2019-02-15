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
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Service that makes easier to work with company (team) memberships.
 *
 * It also handles cache invalidation.
 */
final class TeamMembershipManager implements TeamMembershipManagerInterface {

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
   * The cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  private $cacheTagsInvalidator;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

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
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CompanyMembersControllerFactoryInterface $company_members_controller_factory, DeveloperControllerInterface $developer_controller, DeveloperCompaniesCacheInterface $developer_companies_cache, CacheTagsInvalidatorInterface $cache_tags_invalidator, LoggerInterface $logger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->companyMembersControllerFactory = $company_members_controller_factory;
    $this->developerController = $developer_controller;
    $this->developerCompaniesCache = $developer_companies_cache;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->logger = $logger;
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
    /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorageInterface $team_member_role_storage */
    $team_member_role_storage = $this->entityTypeManager->getStorage('team_member_role');
    /** @var \Drupal\user\UserInterface[] $users_by_mail */
    $users_by_mail = array_reduce($this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $developers]), function (array $carry, UserInterface $user) {
      $carry[$user->getEmail()] = $user;
      return $carry;
    }, []);
    foreach ($developers as $developer) {
      $controller->removeMember($developer);
      // Remove team member's roles from Drupal.
      if (array_key_exists($developer, $users_by_mail)) {
        /** @var \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface[] $team_member_roles_in_teams */
        $team_member_roles_in_teams = $team_member_role_storage->loadByDeveloper($users_by_mail[$developer]);
        foreach ($team_member_roles_in_teams as $team_member_roles_in_team) {
          try {
            $team_member_roles_in_team->delete();
          }
          catch (EntityStorageException $e) {
            $this->logger->critical("Failed to remove %developer team member's roles in %team team with its membership.", [
              '%developer' => $developer,
              '%team' => $team_member_roles_in_team->getTeam()->id(),
            ]);
          }
        }
      }
    }
    $this->invalidateCaches($team, $developers);
  }

  /**
   * {@inheritdoc}
   */
  public function getTeams(string $developer): array {
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $entity */
    $entity = $this->entityTypeManager->getStorage('developer')->load($developer);
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
    $team_entity = $this->entityTypeManager->getStorage('team')->load($team);
    // This invalidates every occupancies where $team has appeared.
    $tags = $team_entity->getCacheTags();
    // This invalidates cache on team listing page(s) because the list
    // of accessible teams for user(s) has changed.
    // (If we find this too much, later we can tag team listing pages with a
    // "team_list:{$developer_email}" tag and only invalidate those developers'
    // team listing pages whose membership state has changed.)
    $tags = array_merge($tags, $this->entityTypeManager->getDefinition('team')->getListCacheTags());
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
    // Enforce re-evaluation of API product entity access.
    $this->entityTypeManager->getAccessControlHandler('api_product')->resetCache();
    // Prevents circular reference between the services:
    // apigee_edge_teams.team_permissions ->
    // apigee_edge_teams.team_membership_manager ->
    // apigee_edge_teams.team_member_api_product_access_handler.
    // This call is just a helper for us to ensure the static cache of the
    // Team member API Product access handler gets cleared when this method
    // gets called. This is especially useful in testing. So calling
    // \Drupal::service() should be fine.
    \Drupal::service('apigee_edge_teams.team_member_api_product_access_handler')->resetCache();
  }

}
