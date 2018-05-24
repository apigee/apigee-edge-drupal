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

use Drupal\apigee_edge\Plugin\EdgeOauthKeyTypeInterface;
use Drupal\apigee_edge\Plugin\EdgeOauthTokenKeyTypeInterface;
use Drupal\key\KeyInterface;
use Http\Message\Authentication;

/**
 * The API credentials for OAuth.
 */
class OauthCredentials extends Credentials {

  /**
   * The OAuth token key entity.
   *
   * @var \Drupal\key\KeyInterface
   */
  protected $keyToken;

  /**
   * OauthCredentials constructor.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity which stores the API credentials.
   * @param \Drupal\key\KeyInterface $key_token
   *   The OAuth token key entity.
   *
   * @throws \InvalidArgumentException
   *   An InvalidArgumentException is thrown if the key type
   *   does not implement EdgeOauthKeyTypeInterface and the
   *   token key does not implement EdgeOauthTokenKeyTypeInterface.
   */
  public function __construct(KeyInterface $key, KeyInterface $key_token) {
    parent::__construct($key);

    if (!($key->getKeyType() instanceof EdgeOauthKeyTypeInterface)) {
      throw new \InvalidArgumentException("Type of {$key->id()} OAuth key does not implement EdgeOauthKeyTypeInterface.");
    }
    if (!($key_token->getKeyType() instanceof EdgeOauthTokenKeyTypeInterface)) {
      throw new \InvalidArgumentException("Type of {$key_token->id()} OAuth token key does not implement EdgeOauthTokenKeyTypeInterface.");
    }

    $this->keyToken = $key_token;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthentication(): Authentication {
    return $this->keyType->getAuthenticationMethod($this->key, $this->keyToken);
  }

}
