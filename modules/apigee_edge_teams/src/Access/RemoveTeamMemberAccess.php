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
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Checks access on remove team member route.
 */
final class RemoveTeamMemberAccess implements AccessInterface {

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  private $teamMembershipManager;

  /**
   * The manage team members access checker.
   *
   * @var \Drupal\apigee_edge_teams\Access\ManageTeamMembersAccess
   */
  private $manageTeamMembersAccess;

  /**
   * RemoveTeamMemberAccess constructor.
   *
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   */
  public function __construct(TeamMembershipManagerInterface $team_membership_manager, ConfigFactoryInterface $config) {
    $this->teamMembershipManager = $team_membership_manager;
    $this->manageTeamMembersAccess = new ManageTeamMembersAccess($team_membership_manager, $config);
  }

  /**
   * Checks access on remove team member route.
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
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface|null $developer */
    $developer = $route_match->getParameter('developer');

    if ($developer === NULL) {
      return AccessResult::forbidden('The {developer} parameter is missing from route.');
    }

    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface|null $team */
    $team = $route_match->getParameter('team');
    if ($team !== NULL) {
      if (!in_array($developer->getEmail(), $this->teamMembershipManager->getMembers($team->id()))) {
        return AccessResultForbidden::forbidden("The {$developer->getEmail()} developer is not member of the {$team->id()} team.");
      }
    }

    return $this->manageTeamMembersAccess->access($route_match, $account);
  }

}
