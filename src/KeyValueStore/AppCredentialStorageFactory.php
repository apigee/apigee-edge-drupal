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

namespace Drupal\apigee_edge\KeyValueStore;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;

/**
 * App credential storage factory for private temp storage.
 *
 * This implementation together with the private temp storage combines the best
 * from both worlds. We can store app credentials loaded by a user in it's
 * own in-memory private temp storage which is not in the database or any other
 * persistent storage. This is good, because app credentials should not be
 * "cached" in Drupal for security reasons. App credentials are only available
 * (stored) for the current page request.
 *
 * @see \Drupal\Core\KeyValueStore\KeyValueExpirableFactory
 * @see \Drupal\Core\TempStore\PrivateTempStoreFactory
 */
class AppCredentialStorageFactory implements KeyValueExpirableFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function get($collection) {
    return new AppCredentialStorage($collection);
  }

}
