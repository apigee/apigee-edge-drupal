<?php

/**
 * Copyright 2023 Google Inc.
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

use Apigee\Edge\Api\ApigeeX\Structure\AppGroupMembership;

/**
 * AppGroup membership object cache definition.
 */
interface AppGroupMembershipObjectCacheInterface {

  /**
   * Saves appgroup membership object to the cache.
   *
   * @param string $appGroup
   *   Name of a appgroup.
   * @param \Apigee\Edge\Api\ApigeeX\Structure\AppGroupMembership $membership
   *   Membership object with the members.
   */
  public function saveMembership(string $appGroup, AppGroupMembership $membership): void;

  /**
   * Removes appgroup membership object from the cache.
   *
   * @param string $appGroup
   *   Name of a appgroup.
   */
  public function removeMembership(string $appGroup): void;

  /**
   * Invalidate membership objects by tags.
   *
   * @param array $tags
   *   Array of cache tags.
   */
  public function invalidateMemberships(array $tags): void;

  /**
   * Returns membership object from the cache.
   *
   * @param string $appGroup
   *   Name of a appgroup.
   *
   * @return \Apigee\Edge\Api\ApigeeX\Structure\AppGroupMembership|null
   *   Membership object with the members or null if no entry found for the
   *   given appgroup in the cache.
   */
  public function getMembership(string $appGroup): ?AppGroupMembership;

}
