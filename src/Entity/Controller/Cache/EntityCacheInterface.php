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

namespace Drupal\apigee_edge\Entity\Controller\Cache;

use Apigee\Edge\Entity\EntityInterface;

/**
 * Base definition of the entity cache used by controllers.
 *
 * Stores entities returned by entity controllers.
 */
interface EntityCacheInterface {

  /**
   * Saves entities to the cache.
   *
   * @param \Apigee\Edge\Entity\EntityInterface[] $entities
   *   Array of entities.
   */
  public function saveEntities(array $entities): void;

  /**
   * Removes entities from the cache by their ids.
   *
   * @param string[] $ids
   *   Array of entity ids.
   */
  public function removeEntities(array $ids): void;

  /**
   * Returns entities from the cache.
   *
   * @param array $ids
   *   Array of entity ids.
   *   If an empty array is passed all currently stored gets returned.
   *
   * @return \Apigee\Edge\Entity\EntityInterface[]
   *   Array of entities.
   */
  public function getEntities(array $ids = []): array;

  /**
   * Returns an entity from the cache by its id.
   *
   * @param string $id
   *   Entity id.
   *
   * @return \Apigee\Edge\Entity\EntityInterface|null
   *   The entity if it is in the cache, null otherwise.
   */
  public function getEntity(string $id): ?EntityInterface;

  /**
   * Changes whether all entities in the cache or not.
   *
   * @param bool $all_entities_in_cache
   *   State to be set.
   */
  public function allEntitiesInCache(bool $all_entities_in_cache): void;

  /**
   * Returns whether all entities in cache or not.
   *
   * @return bool
   *   Current state.
   */
  public function isAllEntitiesInCache(): bool;

}
