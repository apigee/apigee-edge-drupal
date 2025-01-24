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

namespace Drupal\apigee_edge_teams\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access handler for Team entities.
 */
final class TeamAccessHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The organization controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface
   */
  private $orgController;

  /**
   * The developer storage.
   *
   * @var \Drupal\apigee_edge\Entity\Storage\DeveloperStorageInterface
   */
  private $developerStorage;

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  private $teamMembershipManager;

  /**
   * TeamAccessHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, TeamMembershipManagerInterface $team_membership_manager, OrganizationControllerInterface $org_controller) {
    parent::__construct($entity_type);
    $this->developerStorage = $entity_type_manager->getStorage('developer');
    $this->teamMembershipManager = $team_membership_manager;
    $this->orgController = $org_controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('apigee_edge_teams.team_membership_manager'),
      $container->get('apigee_edge.controller.organization')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\Core\Access\AccessResult $result */
    $result = parent::checkAccess($entity, $operation, $account);

    if ($result->isNeutral()) {
      $permissions = [
        "$operation any {$entity->getEntityTypeId()}",
      ];
      if ($this->entityType->getAdminPermission()) {
        $permissions[] = $this->entityType->getAdminPermission();
      }
      $result = AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');

      if ($result->isNeutral() && $operation === 'view') {
        if ($account->isAuthenticated()) {
          // Grant access to the user if it is a member of the Team.
          // (Reminder, anonymous user can not be member of a team.
          /** @var \Drupal\apigee_edge\Entity\DeveloperInterface|null $developer */
          $developer = $this->developerStorage->load($account->getEmail());
          // Checking for ApigeeX organization.
          if ($this->orgController->isOrganizationApigeeX()) {
            // Argument #2 in getTeams() is required for checking the AppGroup members and not required for Edge.
            $developer_team_ids = $this->teamMembershipManager->getTeams($account->getEmail(), $entity->id());
          }
          else {
            $developer_team_ids = $developer->getCompanies();
          }

          $developer_team_access = FALSE;

          if ($developer && in_array($entity->id(), $developer_team_ids)) {
            $developer_team_access = TRUE;
          }
          else {
            // Check if current developer is a member of the team and has the permision
            // to view more than 100 teams.
            // Should not run for developer with less than 100 teams.
            if ($account->hasPermission('view extensive team list') && (count($developer_team_ids) >= 100)) {
              $team_members = $this->teamMembershipManager->getMembers($entity->id());
              if (in_array($account->getEmail(), $team_members)) {
                $developer_team_access = TRUE;
              }
            }
          }

          if ($developer_team_access === TRUE) {
            $result = AccessResult::allowed();
            // Ensure that access is evaluated again when the team or the
            // developer entity changes.
            $result->addCacheableDependency($entity);
            $result->addCacheableDependency($developer);
          }
        }
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $result = parent::checkCreateAccess($account, $context, $entity_bundle);
    if ($result->isNeutral()) {
      $permissions = [
        "create {$this->entityType->id()}",
      ];
      if ($this->entityType->getAdminPermission()) {
        $permissions[] = $this->entityType->getAdminPermission();
      }

      $result = AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
    }

    return $result;
  }

}
