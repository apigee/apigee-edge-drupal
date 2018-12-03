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
use Drupal\apigee_edge\MemoryCacheFactoryInterface;

/**
 * Default developer cache implementation.
 *
 * Generates additional cache entries for developers by using their
 * email addresses as cache ids. This way developers can be served from
 * cache both by their developer id (UUID) an email address.
 */
final class DeveloperCache extends EntityCache implements EntityCacheInterface {

  /**
   * An internal cache for developer ids and emails.
   *
   * Associative array, keys are developer ids and values are email addresses.
   *
   * @var array
   */
  private $developerIdEmailMap = [];

  /**
   * DeveloperCache constructor.
   *
   * @param \Drupal\apigee_edge\MemoryCacheFactoryInterface $memory_cache_factory
   *   The memory cache factory service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface $entity_id_cache
   *   The developer entity id cache.
   */
  public function __construct(MemoryCacheFactoryInterface $memory_cache_factory, EntityIdCacheInterface $entity_id_cache) {
    parent::__construct($memory_cache_factory, $entity_id_cache, 'developer');
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareCacheItem(EntityInterface $entity): array {
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperInterface $entity */
    $item = parent::prepareCacheItem($entity);
    // Add developer's email as tag to generated cache items by the parent
    // class.
    foreach ($item as $cid => $developer) {
      $item[$cid]['tags'][] = $entity->getEmail();
    }

    $this->developerIdEmailMap[$entity->getDeveloperId()] = $entity->getEmail();

    return $item;
  }

  /**
   * {@inheritdoc}
   */
  protected function doSaveEntities(array $entities): void {
    parent::doSaveEntities($entities);
    $items = [];
    foreach ($entities as $entity) {
      // Add new cache item that uses developer's email address as a cid instead
      // of developer's id (UUID).
      $items[$entity->getEmail()] = [
        'data' => $entity,
        'tags' => [$entity->getDeveloperId(), $entity->getEmail()],
      ];
    }
    $this->cacheBackend->setMultiple($items);
  }

  /**
   * {@inheritdoc}
   */
  protected function doRemoveEntities(array $ids): void {
    if (!empty($ids)) {
      // If ids are developer ids (UUIDs).
      $dev_id_email_address_matches = array_intersect_key($this->developerIdEmailMap, array_flip($ids));
      // If ids are email addresses.
      $dev_id_email_address_matches += array_flip(array_intersect_key(array_flip($this->developerIdEmailMap), array_flip($ids)));
      // Because all cached entries tagged with email address and developer
      // id (UUID) this should invalidate everything that is needed in this
      // cache...
      $this->cacheBackend->invalidateTags($ids);
      // ... although if $ids are developer ids (UUIDs) then the entity id cache
      // does not get invalidated properly in parent.
      $this->entityIdCache->removeIds($dev_id_email_address_matches);
      // Remove removed items from the internal cache.
      $this->developerIdEmailMap = array_diff_key($this->developerIdEmailMap, $dev_id_email_address_matches);
    }
  }

}
