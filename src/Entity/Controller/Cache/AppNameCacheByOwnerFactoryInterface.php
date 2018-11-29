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
 * Base definition of the app name cache by app owner service.
 *
 * This service should be used to get a dedicated app name cache instance
 * for an app owner.
 */
interface AppNameCacheByOwnerFactoryInterface {

  /**
   * Returns the same app name cache instance for an owner.
   *
   * @param string $owner
   *   Developer id (UUID), email address or a company's company name.
   *
   * @return \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface
   *   The app name cache instance that belongs to the owner.
   */
  public function getAppNameCache(string $owner): EntityIdCacheInterface;

}
