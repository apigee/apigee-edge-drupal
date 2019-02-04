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

/**
 * Base definition of the general app cache service for an app owner.
 *
 * This generates an app cache for apps of a company or a developer.
 */
final class AppCacheByOwnerFactory implements AppCacheByOwnerFactoryInterface {

  /**
   * Internal cache for created instances.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerInterface[]
   */
  private $instances = [];

  /**
   * The app name cache by owner factory service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface
   */
  private $appNameCacheByOwnerFactory;

  /**
   * The app cache service that stores app by their app id (UUID).
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface
   */
  private $appCache;

  /**
   * AppCacheByAppOwnerFactory constructor.
   *
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache
   *   The app cache service that stores app by their app id (UUID).
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner_factory
   *   The app name cache by owner factory service.
   */
  public function __construct(AppCacheInterface $app_cache, AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner_factory) {
    $this->appCache = $app_cache;
    $this->appNameCacheByOwnerFactory = $app_name_cache_by_owner_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getAppCache(string $owner): AppCacheByOwnerInterface {
    if (!isset($this->instances[$owner])) {
      $this->instances[$owner] = new AppCacheByOwner($owner, $this->appCache, $this->appNameCacheByOwnerFactory);
    }

    return $this->instances[$owner];
  }

}
