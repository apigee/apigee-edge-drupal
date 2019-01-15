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

use Apigee\Edge\Api\Management\Entity\AppInterface;

/**
 * Base definition of an app cache for app controllers.
 *
 * This cache stores app by using their app id as a cache id.
 */
interface AppCacheInterface extends EntityCacheInterface {

  /**
   * Returns an app from the cache by its owner.
   *
   * Only developer id (UUID) and company name can be used as owner because
   * this is what is stored on the app entities.
   *
   * @param string $owner
   *   Developer id (UUID) or company name.
   *
   * @return \Apigee\Edge\Api\Management\Entity\AppInterface[]
   *   Array of apps that belongs to this owner in cache or null if no entry
   *   found in cache for this owner.
   */
  public function getAppsByOwner(string $owner): ?array;

  /**
   * Remove all apps from the cache by their owner.
   *
   * Only developer id (UUID) and company name can be used as owner because
   * this is what is stored on the app entities.
   *
   * @param string $owner
   *   Developer id (UUID) or company name.
   */
  public function removeAppsByOwner(string $owner): void;

  /**
   * Returns the owner of an app.
   *
   * @param \Apigee\Edge\Api\Management\Entity\AppInterface $app
   *   App entity.
   *
   * @return string
   *   Either developer id (UUID) or company name.
   */
  public function getAppOwner(AppInterface $app): string;

}
