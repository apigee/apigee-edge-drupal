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
 * Base definition of the entity id cache service used by controllers.
 *
 * See the interface definition for more details.
 *
 * Always create a dedicated instance from this for an entity type!
 *
 * @internal
 */
class EntityIdCache implements EntityIdCacheInterface {

  /**
   * Stored entity ids.
   *
   * As an associative array where keys and values as the same.
   *
   * @var string[]
   */
  private $ids = [];

  /**
   * Indicates whether all entity ids in cache all or not.
   *
   * @var bool
   */
  private $allIdsInCache = FALSE;

  /**
   * {@inheritdoc}
   */
  final public function saveIds(array $ids): void {
    // Store entity ids in an associative array because it is easier to work
    // with them.
    $this->ids += array_combine($ids, $ids);
  }

  /**
   * Allows to perform additional tasks after entity ids got saved to cache.
   *
   * @param array $ids
   *   Array of entity ids.
   */
  protected function doSaveIds(array $ids) {}

  /**
   * {@inheritdoc}
   */
  final public function saveEntities(array $entities): void {
    $ids = array_map(function (EntityInterface $entity) {
      return $this->getEntityId($entity);
    }, $entities);
    $this->saveIds($ids);
  }

  /**
   * {@inheritdoc}
   */
  final public function removeIds(array $ids): void {
    $this->ids = array_diff($this->ids, $ids);
    // If ids is empty now, reset the state. Cache can be marked as "complete"
    // still by calling the setter method if needed.
    if (empty($this->ids)) {
      $this->allIdsInCache = FALSE;
    }
    $this->doRemoveIds($ids);
  }

  /**
   * Allows to perform additional tasks after entity ids got deleted from cache.
   *
   * @param array $ids
   *   Array of entity ids.
   */
  protected function doRemoveIds(array $ids) {}

  /**
   * {@inheritdoc}
   */
  final public function getIds(): array {
    // We return a non-associative array because this is what getEntityIds()
    // returns.
    return array_values($this->ids);
  }

  /**
   * {@inheritdoc}
   */
  final public function allIdsInCache(bool $all_ids_in_cache): void {
    $this->allIdsInCache = $all_ids_in_cache;
  }

  /**
   * {@inheritdoc}
   */
  final public function isAllIdsInCache(): bool {
    return $this->allIdsInCache;
  }

  /**
   * Returns the unique id of an entity that getEntityIds() returns as well.
   *
   * @param \Apigee\Edge\Entity\EntityInterface $entity
   *   Entity object.
   *
   * @return string
   *   Unique id from the entity that getEntityIds() returns as well.
   */
  protected function getEntityId(EntityInterface $entity): string {
    return $entity->id();
  }

  /**
   * Prevents data stored in entity id cache from being serialized.
   */
  public function __sleep() {
    return [];
  }

}
