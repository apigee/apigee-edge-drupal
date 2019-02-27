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

use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an interface to list available team permissions.
 *
 * Based on Drupal core's PermissionHandlerInterface.
 *
 * @see \Drupal\user\PermissionHandlerInterface
 */
interface TeamPermissionHandlerInterface {

  /**
   * Gets all available team permissions.
   *
   * @return \Drupal\apigee_edge_teams\Structure\TeamPermission[]
   *   Array of team permissions.
   */
  public function getPermissions(): array;

  /**
   * Returns team permissions of a developer within a team.
   *
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team entity, the developer is not necessarily member of the team.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return array
   *   Array of team permissions names.
   *
   * @throws \Drupal\apigee_edge_teams\Exception\InvalidArgumentException
   */
  public function getDeveloperPermissionsByTeam(TeamInterface $team, AccountInterface $account): array;

}
