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
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service that makes easier to work with company (team) memberships.
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
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CompanyMembersControllerFactoryInterface $company_members_controller_factory, DeveloperControllerInterface $developer_controller, DeveloperCompaniesCacheInterface $developer_companies_cache) {
    $this->developerStorage = $entity_type_manager->getStorage('developer');
    $this->companyMembersControllerFactory = $company_members_controller_factory;
    $this->developerController = $developer_controller;
    $this->developerCompaniesCache = $developer_companies_cache;
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
    $this->invalidateCaches($developers);
  }

  /**
   * {@inheritdoc}
   */
  public function removeMembers(string $team, array $developers): void {
    $controller = $this->companyMembersControllerFactory->companyMembersController($team);
    foreach ($developers as $developer) {
      $controller->removeMember($developer);
    }
    $this->invalidateCaches($developers);
  }

  /**
   * {@inheritdoc}
   */
  public function getTeams(string $developer): array {
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $entity */
    $entity = $this->developerStorage->loadUnchanged($developer);
    // Developer entity's getCompanies() method should return the list of
    // companies where the developer is member.
    // @see \Drupal\apigee_edge\Entity\Developer::getCompanies()
    return $entity->getCompanies();
  }

  /**
   * Invalidates developer related caches when membership changes.
   *
   * @param array $developers
   *   Array of developer email addresses.
   */
  private function invalidateCaches(array $developers): void {
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
