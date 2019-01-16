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

namespace Drupal\apigee_edge\Entity;

/**
 * Definition of the developer company membership cache.
 *
 * @internal This cache only exists to allow developers to reload list
 * of companies returned by Developer::getCompanies().
 *
 * @see \Drupal\apigee_edge\Entity\Developer::getCompanies()
 */
interface DeveloperCompaniesCacheInterface {

  /**
   * Returns companies of a developer.
   *
   * @param string $id
   *   Developer id.
   *
   * @return string[]|null
   *   Array of company names or NULL if information is not yet available.
   */
  public function getCompanies(string $id): ?array;

  /**
   * Saves developers' companies to cache.
   *
   * @param \Apigee\Edge\Api\Management\Entity\DeveloperInterface[] $developers
   *   Developer entities.
   */
  public function saveCompanies(array $developers): void;

  /**
   * Removes cached company information of a developer.
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
