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
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Config\ConfigFactoryInterface;
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
   * Name of the config that contains the manage team team-level permission.
   */
  const CONFIG_NAME = 'apigee_edge_teams.team_permissions';

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config;

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  private $teamMembershipManager;

  /**
   * ManageTeamMembersAccess constructor.
   *
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   */
  public function __construct(TeamMembershipManagerInterface $team_membership_manager, ConfigFactoryInterface $config) {
    $this->config = $config;
    $this->teamMembershipManager = $team_membership_manager;
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
    if ($team === NULL) {
      return AccessResult::forbidden('The {team} parameter is missing from route.');
    }
    $result = AccessResultAllowed::allowedIfHasPermissions($account, ['administer team', 'manage team members'], 'OR')->cachePerPermissions();

    if ($result->isNeutral()) {
      $config = $this->config->get(static::CONFIG_NAME);
      $result = AccessResultAllowed::allowedIf($config->get('team_manage_members'))->addCacheTags(['config:' . static::CONFIG_NAME]);
      if ($result->isAllowed()) {
        try {
          $teams = $this->teamMembershipManager->getTeams($account->getEmail());
        }
        catch (\Exception $e) {
          $teams = [];
        }
        $result = AccessResultAllowed::allowedIf(in_array($team->id(), $teams))->cachePerUser()->addCacheTags(['config:' . static::CONFIG_NAME]);
      }
    }

    return $result;
  }

}
