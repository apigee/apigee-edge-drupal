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

use Apigee\Edge\Api\Management\Structure\CompanyMembership;

/**
 * Company membership object cache definition.
 */
interface CompanyMembershipObjectCacheInterface {

  /**
   * Saves company membership object to the cache.
   *
   * @param string $company
   *   Name of a company.
   * @param \Apigee\Edge\Api\Management\Structure\CompanyMembership $membership
   *   Membership object with the members.
   */
  public function saveMembership(string $company, CompanyMembership $membership): void;

  /**
   * Removes company membership object from the cache.
   *
   * @param string $company
   *   Name of a company.
   */
  public function removeMembership(string $company): void;

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
   * @param string $company
   *   Name of a company.
   *
   * @return \Apigee\Edge\Api\Management\Structure\CompanyMembership|null
   *   Membership object with the members or null if no entry found for the
   *   given company in the cache.
   */
  public function getMembership(string $company): ?CompanyMembership;

}
