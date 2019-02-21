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

namespace Drupal\apigee_edge\Entity\Controller;

use Apigee\Edge\Structure\PagerInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface;

/**
 * For those controllers that supports paginated entity id listing.
 *
 * @see \Apigee\Edge\Controller\PaginatedEntityIdListingControllerInterface
 */
trait CachedPaginatedEntityIdListingControllerTrait {

  /**
   * The decorated entity controller from the SDK.
   *
   * We did not added a return type because this way all entity controller's
   * decorated() method becomes compatible with this declaration.
   *
   * @return \Apigee\Edge\Controller\PaginatedEntityIdListingControllerInterface
   *   An entity controller that extends these interfaces.
   */
  abstract protected function decorated();

  /**
   * Entity id cache used by the entity controller.
   *
   * @return \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface
   *   The entity id cache.
   */
  abstract protected function entityIdCache(): EntityIdCacheInterface;

  /**
   * {@inheritdoc}
   */
  abstract protected function extractSubsetOfAssociativeArray(array $assoc_array, int $limit, ?string $start_key = NULL): array;

  /**
   * {@inheritdoc}
   */
  public function getEntityIds(PagerInterface $pager = NULL): array {
    if ($this->entityIdCache()->isAllIdsInCache()) {
      if ($pager === NULL) {
        return $this->entityIdCache()->getIds();
      }
      else {
        return $this->extractSubsetOfAssociativeArray($this->entityIdCache()->getIds(), $pager->getLimit(), $pager->getStartKey());
      }
    }

    $ids = $this->decorated()->getEntityIds($pager);
    $this->entityIdCache()->saveIds($ids);

    if ($pager === NULL) {
      $this->entityIdCache()->allIdsInCache(TRUE);
    }

    return $ids;
  }

}
