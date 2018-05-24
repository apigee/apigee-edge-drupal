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

namespace Drupal\apigee_edge\Plugin;

use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyTypeMultivalueInterface;

/**
 * Defines an interface for Apigee Edge OAuth Token Key Type plugins.
 */
interface EdgeOauthTokenKeyTypeInterface extends KeyTypeMultivalueInterface {

  /**
   * Gets the OAuth access token.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string|null
   *   The OAuth access token.
   */
  public function getAccessToken(KeyInterface $key): ?string;

  /**
   * Gets the OAuth refresh token.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string|null
   *   The OAuth refresh token.
   */
  public function getRefreshToken(KeyInterface $key): ?string;

  /**
   * Gets the OAuth scope.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string|null
   *   The OAuth scope.
   */
  public function getScope(KeyInterface $key): ?string;

  /**
   * Gets the OAuth token type.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string|null
   *   The OAuth token type.
   */
  public function getTokenType(KeyInterface $key): ?string;

  /**
   * Gets the OAuth expiration time in seconds.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return int|null
   *   The OAuth expiration time.
   */
  public function getExpiresIn(KeyInterface $key): ?int;

  /**
   * Gets the OAuth expiration timestamp.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return int|null
   *   The OAuth expiration time.
   */
  public function getExpires(KeyInterface $key): ?int;

  /**
   * Resets the  OAuth expiration timestamp.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   */
  public function resetExpires(KeyInterface $key);

}
