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

use Drupal\apigee_edge_teams\Exception\InvalidArgumentException;
use Drupal\Core\Config\Entity\ConfigEntityStorage;

/**
 * Team role entity storage.
 */
class TeamRoleStorage extends ConfigEntityStorage implements TeamRoleStorageInterface {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\apigee_edge_teams\Exception\InvalidArgumentException
   *   If team role does not exist.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   If changes could not be saved.
   */
  public function changePermissions(string $role_name, array $permissions): void {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamRoleInterface $role */
    $role = $this->load($role_name);
    if ($role === NULL) {
      throw new InvalidArgumentException("Team role with name does not exist: {$role_name}");
    }
    // Grant new permissions for the role.
    $grant = array_filter($permissions);
    if (!empty($grant)) {
      foreach (array_keys($grant) as $permission) {
        $role->grantPermission($permission);
      }
    }
    $revoke = array_diff_assoc($permissions, $grant);
    if (!empty($revoke)) {
      foreach (array_keys($revoke) as $permission) {
        $role->revokePermission($permission);
      }
    }
    $role->trustData()->save();
  }

}
