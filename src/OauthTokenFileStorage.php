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
use Drupal\Core\Site\Settings;

/**
 * Storing and returning OAuth access token data.
 */
class OauthTokenFileStorage implements OauthTokenStorageInterface {

  const OAUTH_TOKEN_FILE_PATH_SETTINGS_KEY = 'apigee_edge_oauth_token_file_path';

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
   * An internally cached token data store.
   *
   * @var array
   */
  private static $token_data;

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(): ?string {
    $token_data = $this->getTokenData();
    return $token_data['access_token'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenType(): ?string {
    $token_data = $this->getTokenData();
    return $token_data['token_type'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRefreshToken(): ?string {
    $token_data = $this->getTokenData();
    return $token_data['refresh_token'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getScope(): string {
    $token_data = $this->getTokenData();
    return $token_data['scope'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpires(): int {
    $token_data = $this->getTokenData();
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
    $token_data = $this->getTokenData();
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
    $data['expires'] = isset($data['expires_in']) ? $data['expires_in'] + time() : ($data['expires'] ?? -1);
    // Remove the expires_in data.
    unset($data['expires_in']);

    // Gets the file directory so we can make sure it exists.
    $file_path = dirname(static::oauthTokenPath());
    file_prepare_directory($file_path, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

    // Write the obfuscated token data to a private file.
    file_unmanaged_save_data(base64_encode(serialize($data)), static::oauthTokenPath(), FILE_EXISTS_REPLACE);

    // Update the cached value.
    static::$token_data = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function removeToken(): void {
    // Remove the token data from storage.
    $this->saveToken([]);
  }

  /**
   * Gets the storage location for OAuth token data.
   */
  public static function oauthTokenPath() {
    static $token_path;

    $token_path = $token_path
      ?? rtrim(Settings::get(static::OAUTH_TOKEN_FILE_PATH_SETTINGS_KEY, 'private://.apigee_edge'), " \t\n\r\0\x0B\\/") . '/oauth.dat';

    return $token_path;
  }

  /**
   * Gets all token data from storage.
   *
   * @param bool $reset
   *   Whether or not to reload the token data.
   *
   * @return array
   *   The token data.
   */
  protected function getTokenData($reset = FALSE): array {
    // Load from storage if the cached value is empty.
    if ($reset || empty(static::$token_data)) {
      static::$token_data = $this->getFromStorage();
    }

    return static::$token_data;
  }

  /**
   * Gets the token data from storage.
   *
   * @return array
   *   The token data or an empty array.
   */
  protected function getFromStorage(): array {
    // Get the token data from the file store.
    if ($raw_data = file_get_contents(static::oauthTokenPath())) {
      $data = unserialize(base64_decode($raw_data));
    }
    return $data ?: [
      'access_token' => NULL,
      'token_type' => NULL,
      'expires' => NULL,
      'refresh_token' => NULL,
      'scope' => '',
    ];
  }

}
