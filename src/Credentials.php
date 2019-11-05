<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge;

use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\key\KeyInterface;
use Http\Message\Authentication;

/**
 * The API credentials.
 *
 * @todo: move to \Drupal\apigee_edge\Connector namespace.
 */
class Credentials implements CredentialsInterface {

  /**
   * The key entity which stores the API credentials.
   *
   * @var \Drupal\key\KeyInterface
   */
  protected $key;

  /**
   * The key type of the key entity.
   *
   * @var \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface
   */
  protected $keyType;

  /**
   * Credentials constructor.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity which stores the API credentials.
   *
   * @throws \InvalidArgumentException
   *   An InvalidArgumentException is thrown if the key type
   *   does not implement EdgeKeyTypeInterface.
   */
  public function __construct(KeyInterface $key) {
    if (!(($key_type = $key->getKeyType()) instanceof EdgeKeyTypeInterface)) {
      throw new \InvalidArgumentException("Type of {$key->id()} key does not implement EdgeKeyTypeInterface.");
    }

    $this->key = $key;
    $this->keyType = $key_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthentication(): Authentication {
    return $this->keyType->getAuthenticationMethod($this->key);
  }

  /**
   * {@inheritdoc}
   */
  public function getKey(): KeyInterface {
    return $this->key;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyType(): EdgeKeyTypeInterface {
    return $this->keyType;
  }

}
