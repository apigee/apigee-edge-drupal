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

use Apigee\Edge\Exception\ApiException;
use Apigee\Edge\Structure\PagerInterface;

/**
 * For those controllers that supports paginated entity listing.
 *
 * @see \Apigee\Edge\Controller\PaginatedEntityListingControllerInterface
 */
trait CachedPaginatedEntityListingControllerTrait {

  use EntityCacheAwareControllerTrait;

  /**
   * The decorated entity controller from the SDK.
   *
   * We did not added a return type because this way all entity controller's
   * decorated() method becomes compatible with this declaration.
   *
   * @return \Apigee\Edge\Controller\PaginatedEntityListingControllerInterface
   *   An entity controller that extends these interfaces.
   */
  abstract protected function decorated();

  /**
   * {@inheritdoc}
   */
  public function getEntities(PagerInterface $pager = NULL, string $key_provider = 'id'): array {
    if ($this->entityCache()->isAllEntitiesInCache()) {
      if ($pager === NULL) {
        return $this->entityCache()->getEntities();
      }
      else {
        return $this->extractSubsetOfAssociativeArray($this->entityCache()->getEntities(), $pager->getLimit(), $pager->getStartKey());
      }
    }

    try {
      $entities = $this->decorated()->getEntities($pager, $key_provider);
    }
    catch (ApiException $e) {
      $context = [
        '@message' => (string) $e,
        '@pager_start' => 'Pager start key: ' . ($pager ? $pager->getStartKey() : '-'),
        '@pager_limit' => 'Pager limit: ' . ($pager ? $pager->getLimit() : '-'),
      ];
      watchdog_exception('apigee_edge', $e, 'Could not load paginated entity list. @message %function (line %line of %file). @pager_start @pager_limit <pre>@backtrace_string</pre>', $context);

      if (method_exists($this, 'getEntityIds')
        && method_exists($this, 'load')
        && method_exists($this->decorated(), 'getEntityClass')) {
        $entity_ids = $this->getEntityIds($pager);
        $entities = [];
        foreach ($entity_ids as $id) {
          try {
            $entities[] = $this->load($id);
          }
          catch (ApiException $e) {
            $context = [
              '%controller' => static::class,
              '%id' => $id,
              '@message' => (string) $e,
              '@pager_start' => 'Pager start key: ' . ($pager ? $pager->getStartKey() : '-'),
              '@pager_limit' => 'Pager limit: ' . ($pager ? $pager->getLimit() : '-'),
            ];
            watchdog_exception('apigee_edge', $e, 'Controller %controller failed to load entity with ID %id. @message %function (line %line of %file). @pager_start @pager_limit <pre>@backtrace_string</pre>', $context);
          }
        }
      }
      else {
        throw $e;
      }
    }

    $this->entityCache()->saveEntities($entities);
    if ($pager === NULL) {
      $this->entityCache()->allEntitiesInCache(TRUE);
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  abstract protected function extractSubsetOfAssociativeArray(array $assoc_array, int $limit, ?string $start_key = NULL): array;

}
