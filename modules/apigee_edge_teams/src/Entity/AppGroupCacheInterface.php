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

namespace Drupal\apigee_edge_teams\Entity;

/**
 * Definition of the developer appgroup membership cache.
 *
 * @internal This cache only exists to allow developers to reload list
 * of appgroups returned by Developer::getAppGroups().
 */
interface AppGroupCacheInterface {

  /**
   * Returns appgroups of a developer.
   *
   * @param string $id
   *   Developer id.
   *
   * @return string[]|null
   *   Array of appgroup names or NULL if information is not yet available.
   */
  public function getAppGroups(string $id): ?array;

  /**
   * Saves developers' appgroups to cache.
   *
   * @param \Apigee\Edge\Api\Management\Entity\DeveloperInterface[] $developers
   *   Developer entities.
   */
  public function saveAppGroups(array $developers): void;

  /**
   * Removes cached appgroup information of a developer.
   *
   * @param array $ids
   *   Array of developer ids, if the array is empty all entries gets removed.
   */
  public function remove(array $ids = []): void;

  /**
   * Invalidates cache entries by tag.
   *
   * @param array $tags
   *   Array of cache tags.
   */
  public function invalidate(array $tags): void;

}
