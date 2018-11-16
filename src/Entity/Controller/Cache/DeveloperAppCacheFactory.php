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

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Egulias\EmailValidator\EmailValidatorInterface;

/**
 * Developer app cache factory.
 */
final class DeveloperAppCacheFactory implements DeveloperAppCacheFactoryInterface {

  /**
   * Internal cache for created instances.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\DeveloperAppCacheInterface
   */
  private $instances = [];

  /**
   * The email validator service.
   *
   * @var \Egulias\EmailValidator\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * The memory cache backend.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  private $cacheBackend;

  /**
   * DeveloperAppCacheFactory constructor.
   *
   * @param \Egulias\EmailValidator\EmailValidatorInterface $email_validator
   *   The email validator service.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $cache
   *   The memory cache backend used by the app cache.
   */
  public function __construct(EmailValidatorInterface $email_validator, MemoryCacheInterface $cache) {
    $this->emailValidator = $email_validator;
    $this->cacheBackend = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function developerAppCache(string $owner): DeveloperAppCacheInterface {
    if (!isset($this->instances[$owner])) {
      $this->instances[$owner] = new DeveloperAppCache($owner, $this->emailValidator, $this->cacheBackend);
    }

    return $this->instances[$owner];
  }

}
