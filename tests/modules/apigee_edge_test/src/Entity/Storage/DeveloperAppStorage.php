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

namespace Drupal\apigee_edge_test\Entity\Storage;

use Drupal\apigee_edge\Entity\Storage\DeveloperAppStorage as BaseDeveloperAppStorage;

/**
 * Developer app storage controller for tests.
 */
final class DeveloperAppStorage extends BaseDeveloperAppStorage {

  /**
   * Exposes getFromPersistentCache() as a public method.
   *
   * @param array $ids
   *   Array of entity ids.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of entities from the cache.
   */
  public function getFromCache(array $ids) {
    return $this->getFromPersistentCache($ids);
  }

}
