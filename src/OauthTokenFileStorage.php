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

use Apigee\Edge\HttpClient\Plugin\Authentication\OauthTokenStorageInterface;

/**
 * Storing and returning OAuth access token data.
 */
class OauthTokenFileStorage implements OauthTokenStorageInterface {

  /**
   * The storage location for oauth data.
   */
  const OAUTH_TOKEN_PATH = 'private://.apigee_edge/oauth.dat';

  /**
   * Ensures that token gets refreshed earlier than it expires.
   *
   * Number of seconds extracted from token's expiration date when
   * hasExpired() calculates.
   *
   * @var int
   */
  protected $leeway = 30;

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(): ?string {
    return $this->getFromStorage()['access_token'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenType(): ?string {
    return $this->getFromStorage()['token_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRefreshToken(): ?string {
    return $this->getFromStorage()['refresh_token'];
  }

  /**
   * {@inheritdoc}
   */
  public function getScope(): string {
    return $this->getFromStorage()['scope'];
  }

  /**
   * {@inheritdoc}
   */
  public function getExpires(): int {
    $token_data = $this->getFromStorage();
    return $token_data['expires'] ?? -1;
  }

  /**
   * {@inheritdoc}
   */
  public function hasExpired(): bool {
    $expires = $this->getExpires();
    if ($expires !== 0 && ($expires - $this->leeway) > time()) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function markExpired(): void {
    // Gets token data.
    $token_data = $this->getFromStorage();
    // Expire in the past.
    $token_data['expires_in'] = -1;
    // Save the token data.
    $this->saveToken($token_data);
  }

  /**
   * {@inheritdoc}
   */
  public function saveToken(array $data): void {
    // Calculate the cache expiration.
    $data['expires'] = $data['expires_in'] + time();
    // Remove the expires_in data.
    unset($data['expires_in']);

    // Gets the file directory so we can make sure it exists.
    $file_path = dirname(static::OAUTH_TOKEN_PATH);
    file_prepare_directory($file_path, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

    // Write the obfuscated token data to a private file.
    file_unmanaged_save_data(base64_encode(serialize($data)), static::OAUTH_TOKEN_PATH, FILE_EXISTS_REPLACE);
  }

  /**
   * {@inheritdoc}
   */
  public function removeToken(): void {
    file_unmanaged_delete(static::OAUTH_TOKEN_PATH);
  }

  /**
   * Gets the token data from storage.
   *
   * @return array
   *   The token data or an empty array.
   */
  protected function getFromStorage(): array {
    // Get the token data from the file store.
    if ($raw_data = file_get_contents(static::OAUTH_TOKEN_PATH)) {
      $data = unserialize(base64_decode($raw_data));
    }
    return $data ?? [
      'access_token' => NULL,
      'token_type' => NULL,
      'expires' => NULL,
      'refresh_token' => NULL,
      'scope' => '',
    ];
  }

}
