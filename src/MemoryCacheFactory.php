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

namespace Drupal\apigee_edge;

use Drupal\Core\Cache\MemoryCache\MemoryCache;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;

/**
 * Definition of the Apigee Edge memory cache factory service.
 */
final class MemoryCacheFactory implements MemoryCacheFactoryInterface {

  /**
   * The default cache bin prefix.
   *
   * @var string
   */
  private const DEFAULT_CACHE_BIN_PREFIX = 'apigee_edge';

  /**
   * Module specific cache bin prefix.
   *
   * @var string
   */
  private $prefix;

  /**
   * Instantiated memory cache bins.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  private $bins;

  /**
   * MemoryCacheFactory constructor.
   *
   * @param string|null $prefix
   *   (Optional) Module specific prefix for the bin.
   */
  public function __construct(?string $prefix = NULL) {
    $this->prefix = $prefix ?? static::DEFAULT_CACHE_BIN_PREFIX;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin): MemoryCacheInterface {
    $bin = "{$this->prefix}_{$bin}";
    if (!isset($this->bins[$bin])) {
      $this->bins[$bin] = new MemoryCache();
    }
    return $this->bins[$bin];
  }

}
