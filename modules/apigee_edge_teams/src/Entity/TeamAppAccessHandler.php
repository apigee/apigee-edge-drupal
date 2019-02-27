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

use Drupal\apigee_edge_teams\TeamPermissionHandlerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access handler for Team App entities.
 */
final class TeamAppAccessHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private $routeMatch;

  /**
   * The team permissions handler.
   *
   * @var \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface
   */
  private $teamPermissionHandler;

  /**
   * TeamAppAccessHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface $team_permission_handler
   *   The team permissions handler.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, TeamPermissionHandlerInterface $team_permission_handler, RouteMatchInterface $route_match) {
    parent::__construct($entity_type);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    $this->teamPermissionHandler = $team_permission_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('apigee_edge_teams.team_permissions'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamAppInterface $entity */
    $result = parent::checkAccess($entity, $operation, $account);

    if ($result->isNeutral()) {
      $result = $this->checkAccessByPermissions($account);
      if ($result->isNeutral()) {
        /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $team */
        $team = $this->entityTypeManager->getStorage('team')->load($entity->getCompanyName());
        if ($team) {
          // The developer is not member of the team.
          // @see hook_apigee_edge_teams_developer_permissions_by_team_alter()
          $result = $this->checkAccessByTeamMemberPermissions($team, $operation, $account);
          $this->processAccessResult($result, $account);
        }
        else {
          // Probably this could never happen...
          $result = AccessResult::neutral("The team ({$entity->getCompanyName()}) that the team app ({$entity->getAppId()}) belongs does not exist.");
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
      // Applies to "add-form" link template of Team App entity.
      $result = $this->checkAccessByPermissions($account);

      if ($result->isNeutral()) {
        // Applies to "add-form-for-team" link template of Team App entity.
        $team = $this->routeMatch->getParameter('team');
        if ($team) {
          $result = $this->checkAccessByTeamMemberPermissions($team, 'create', $account);
        }
        else {
          $result = AccessResult::neutral("Team parameter has not been found in {$this->routeMatch->getRouteObject()->getPath()} path.");
        }
      }
    }

    return $result;
  }

  /**
   * Performs access check based on a user's site-wide permissions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  private function checkAccessByPermissions(AccountInterface $account): AccessResultInterface {
    $permissions = [
      TeamAppPermissionProvider::MANAGE_TEAM_APPS_PERMISSION,
    ];
    if ($this->entityType->getAdminPermission()) {
      $permissions[] = $this->entityType->getAdminPermission();
    }
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

  /**
   * Performs access check based on a user's team-level permissions.
   *
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team that owns the app.
   * @param string $operation
   *   The entity operation on a team app: view, create, delete, update or
   *   analytics.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  private function checkAccessByTeamMemberPermissions(TeamInterface $team, string $operation, AccountInterface $account): AccessResultInterface {
    $covered_operations = ['view', 'create', 'delete', 'update', 'analytics'];
    if (!in_array($operation, $covered_operations)) {
      return AccessResult::neutral("Team membership based access check does not support {$operation} operation on team apps.");
    }

    if ($account->isAnonymous()) {
      $result = AccessResult::forbidden('Anonymous user can not be member of a team.');
    }
    else {
      $result = AccessResult::allowedIf(in_array("team_app_{$operation}", $this->teamPermissionHandler->getDeveloperPermissionsByTeam($team, $account)));
      // Ensure that access is re-evaluated when the team entity changes.
      $result->addCacheableDependency($team);
    }

    return $result;
  }

  /**
   * Processes access result before it gets returned.
   *
   * Adds necessary cache tags to the access result object.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $result
   *   The access result to be altered if needed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to access check has happened.
   */
  private function processAccessResult(AccessResultInterface $result, AccountInterface $account) {
    // Ensure that access is re-evaluated when developer entity changes.
    if ($account->isAuthenticated() && $result instanceof AccessResult) {
      $developer = $this->entityTypeManager->getStorage('developer')->load($account->getEmail());
      if ($developer) {
        $result->addCacheableDependency($developer);
      }
    }
  }

}
