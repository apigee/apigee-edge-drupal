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

use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\KeyValueStore\MemoryStorage;

/**
 * Default app credential storage implementation that uses memory as a storage.
 *
 * For security reasons it is not recommended to save app credentials to a
 * persistence storage. This class provides a default solution for this.
 *
 * @see \Drupal\apigee_edge\KeyValueStore\AppCredentialStorageFactory
 */
class AppCredentialStorage extends MemoryStorage implements KeyValueStoreExpirableInterface {

  /**
   * {@inheritdoc}
   */
  public function setWithExpire($key, $value, $expire) {
    $this->set($key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function setWithExpireIfNotExists($key, $value, $expire) {
    if ($this->has($key)) {
      $this->set($key, $value);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setMultipleWithExpire(array $data, $expire) {
    $this->setMultiple($data);
  }

}
