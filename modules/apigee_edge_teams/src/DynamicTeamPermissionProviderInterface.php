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

/**
 * Allows modules to provide dynamic team permissions.
 *
 * @see \Drupal\apigee_edge_teams\Entity\TeamAppPermissionProvider
 */
interface DynamicTeamPermissionProviderInterface {

  /**
   * Returns team permissions provided by a module.
   *
   * @return \Drupal\apigee_edge_teams\Structure\TeamPermission[]
   *   Array of team permissions.
   *
   * @see \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface
   */
  public function permissions(): array;

}
