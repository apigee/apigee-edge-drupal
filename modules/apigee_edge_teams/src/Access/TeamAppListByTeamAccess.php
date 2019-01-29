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

use Drupal\apigee_edge_teams\Entity\TeamAppAccessHandler;
use Drupal\apigee_edge_teams\Entity\TeamAppPermissionProvider;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\apigee_edge_teams\TeamMembershipManagerInterface;
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
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  private $teamMembershipManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * TeamAppListByTeamAccess constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TeamMembershipManagerInterface $team_membership_manager) {
    $this->teamMembershipManager = $team_membership_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Grant access to Team app list by team page.
   *
   * Only team members and users with "Manage Team Apps" permission should have
   * access.
   *
   * There is a little redundancy with the implementation here and with the
   * implementation TeamAppAccessHandler. Nice to have, find a way to remove
   * this redundancy.
   *
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team entity from the route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @see \Drupal\apigee_edge_teams\Entity\TeamAppPermissionProvider
   */
  public function access(TeamInterface $team, AccountInterface $account) {
    $result = AccessResult::allowedIfHasPermission($account, TeamAppPermissionProvider::MANAGE_TEAM_APPS_PERMISSION)->cachePerUser();

    if ($result->isNeutral()) {
      $result = AccessResult::allowedIf(in_array($team->id(), $this->teamMembershipManager->getTeams($account->getEmail())))
        ->addCacheTags(['config:' . TeamAppAccessHandler::MEMBER_PERMISSIONS_CONFIG_NAME]);
      if ($account->isAuthenticated()) {
        $developer = $this->entityTypeManager->getStorage('developer')->load($account->getEmail());
        if ($developer) {
          $result->addCacheableDependency($developer);
        }
      }
    }

    return $result->addCacheableDependency($team);
  }

}
