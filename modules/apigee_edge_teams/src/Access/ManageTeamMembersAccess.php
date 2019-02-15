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

use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
use Drupal\apigee_edge_teams\TeamPermissionHandlerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Check access on manage team members routes.
 *
 * @internal
 */
final class ManageTeamMembersAccess implements AccessInterface {

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  private $teamMembershipManager;

  /**
   * The team permission handler.
   *
   * @var \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface
   */
  private $teamPermissionHandler;

  /**
   * ManageTeamMembersAccess constructor.
   *
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface $team_permission_handler
   *   The team permission handler.
   */
  public function __construct(TeamMembershipManagerInterface $team_membership_manager, TeamPermissionHandlerInterface $team_permission_handler) {
    $this->teamMembershipManager = $team_membership_manager;
    $this->teamPermissionHandler = $team_permission_handler;
  }

  /**
   * Grant access to Manage team members pages.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden('This UI only available to logged in users.');
    }
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $team */
    $team = $route_match->getParameter('team');
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface|null $developer */
    $developer = $route_match->getParameter('developer');

    // If the developer parameter is available in the route make sure it is
    // member of the team.
    if ($developer !== NULL) {
      if (!in_array($team->id(), $this->teamMembershipManager->getTeams($developer->getEmail()))) {
        return AccessResultForbidden::forbidden("The {$developer->getEmail()} developer is not member of the {$team->id()} team.");
      }
    }

    $result = AccessResultAllowed::allowedIfHasPermissions($account, ['administer team', 'manage team members'], 'OR')->cachePerPermissions();

    if ($result->isNeutral()) {
      $result = AccessResultAllowed::allowedIf(in_array('team_manage_members', $this->teamPermissionHandler->getDeveloperPermissionsByTeam($team, $account)))->addCacheableDependency($team)->cachePerUser();
    }

    return $result;
  }

}
