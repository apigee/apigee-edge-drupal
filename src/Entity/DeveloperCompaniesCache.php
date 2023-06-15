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

use Drupal\apigee_edge\MemoryCacheFactoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Default non-persistent developer company membership cache implementation.
 */
final class DeveloperCompaniesCache implements DeveloperCompaniesCacheInterface {

  /**
   * The memory cache backend.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  private $backend;

  /**
   * DeveloperCompaniesCache constructor.
   *
   * @param \Drupal\apigee_edge\MemoryCacheFactoryInterface $memory_cache_factory
   *   The memory cache factory service.
   */
  public function __construct(MemoryCacheFactoryInterface $memory_cache_factory) {
    $this->backend = $memory_cache_factory->get('developer_companies');
  }

  /**
   * {@inheritdoc}
   */
  public function getCompanies(string $id): ?array {
    $item = $this->backend->get($id);
    return $item ? $item->data : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function saveCompanies(array $developers): void {
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperInterface $developer */
    foreach ($developers as $developer) {
      $tags = array_merge([
        "developer:{$developer->getDeveloperId()}",
        "developer:{$developer->getEmail()}",
      ], array_map(function (string $company) {
        return "company:{$company}";
      }, $developer->getCompanies()));
      $this->backend->set($developer->id(), $developer->getCompanies(), CacheBackendInterface::CACHE_PERMANENT, $tags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function remove(array $ids = []): void {
    if (empty($ids)) {
      $this->backend->invalidateAll();
    }
    else {
      $this->backend->invalidateMultiple($ids);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $tags): void {
    $this->backend->invalidateTags($tags);
  }

}
