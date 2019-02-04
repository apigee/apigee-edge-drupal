<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\Tests\apigee_edge\Traits;

use Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface;

/**
 * A trait to common functions of entity controller tests.
 */
trait EntityControllerCacheUtilsTrait {

  /**
   * Saves entities into the entity cache and checks the result.
   *
   * @param \Apigee\Edge\Entity\EntityInterface[] $entities
   *   Apigee Edge entities to save into the cache.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface $entity_cache
   *   The entity cache implementation.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface $entity_id_cache
   *   The entity id cache implementation.
   */
  protected function saveAllEntitiesAndValidate(array $entities, EntityCacheInterface $entity_cache, EntityIdCacheInterface $entity_id_cache) {
    // Save the generated entities into the controller cache.
    $entity_cache->saveEntities($entities);
    $this->assertSame($entities, $entity_cache->getEntities());

    // Set cache states to TRUE.
    $entity_cache->allEntitiesInCache(TRUE);
    $this->assertTrue($entity_cache->isAllEntitiesInCache());
    $this->assertTrue($entity_id_cache->isAllIdsInCache());
  }

  /**
   * Checks whether the cache is properly cleared.
   *
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface $entity_cache
   *   The entity cache implementation.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface $entity_id_cache
   *   The entity id cache implementation.
   */
  protected function assertEmptyCaches(EntityCacheInterface $entity_cache, EntityIdCacheInterface $entity_id_cache) {
    $this->assertEmpty($entity_cache->getEntities());
    $this->assertEmpty($entity_id_cache->getIds());
    $this->assertFalse($entity_cache->isAllEntitiesInCache());
    $this->assertFalse($entity_id_cache->isAllIdsInCache());
  }

  /**
   * Gets a random unique ID.
   */
  protected function getRandomUniqueId(): string {
    return $this->container->get('uuid')->generate();
  }

}
