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

namespace Drupal\apigee_edge_teams\Access;

use Drupal\apigee_edge_teams\Entity\TeamAppPermissionProvider;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\apigee_edge_teams\TeamPermissionHandlerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Check access on Team app list by team route.
 *
 * @internal
 */
final class TeamAppListByTeamAccess implements AccessInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The team permission handler service.
   *
   * @var \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface
   */
  private $teamPermissionHandler;

  /**
   * TeamAppListByTeamAccess constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface $team_permission_handler
   *   The team permission handler service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TeamPermissionHandlerInterface $team_permission_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->teamPermissionHandler = $team_permission_handler;
  }

  /**
   * Grant access to Team app list by team page.
   *
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team entity from the route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(TeamInterface $team, AccountInterface $account) {
    $team_app_admin_permission = $this->entityTypeManager->getDefinition('team_app')->getAdminPermission();
    $result = AccessResult::allowedIfHasPermissions($account, [TeamAppPermissionProvider::MANAGE_TEAM_APPS_PERMISSION, $team_app_admin_permission], 'OR')->cachePerUser();

    if ($result->isNeutral()) {
      if ($account->isAuthenticated()) {
        $result = AccessResult::allowedIf(in_array('team_app_view', $this->teamPermissionHandler->getDeveloperPermissionsByTeam($team, $account)));
        $result->addCacheableDependency($account);
      }
    }

    return $result->addCacheableDependency($team);
  }

}
