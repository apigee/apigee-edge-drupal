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

namespace Drupal\apigee_edge_teams\Entity\Storage;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;

/**
 * Storage definition for team role entity.
 */
interface TeamRoleStorageInterface extends ConfigEntityStorageInterface {

  /**
   * Changes permissions of a team role.
   *
   * This function can be used to grant and revoke multiple permissions at once.
   *
   * Based on user_role_change_permissions().
   *
   * @param string $role_name
   *   The ID of a team role.
   * @param array $permissions
   *   An associative array, where the key holds the team permission
   *   name and the value determines whether to grant or revoke that permission.
   *   Any value that evaluates to TRUE will cause the team permission to be
   *   granted. Any value that evaluates to FALSE will cause the team permission
   *   to be revoked. Existing team permissions are not changed, unless
   *   specified in $permissions.
   */
  public function changePermissions(string $role_name, array $permissions): void;

}
