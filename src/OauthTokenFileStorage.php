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

use Drupal\apigee_edge\Exception\OauthTokenStorageException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Stores OAuth token data in a file.
 *
 * @todo: move to \Drupal\apigee_edge\Connector namespace.
 */
final class OauthTokenFileStorage implements OauthTokenStorageInterface {

  /**
   * Default directory of the oauth.dat file.
   *
   * @var string
   */
  public const DEFAULT_DIRECTORY = 'private://.apigee_edge';

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
   * Internal cache for token data.
   *
   * @var array
   *
   * @see getTokenData()
   */
  private $tokenData = [];

  /**
   * Path of the token file.
   *
   * @var string
   */
  private $tokenFilePath;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * OauthTokenFileStorage constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   */
  public function __construct(ConfigFactoryInterface $config, FileSystemInterface $file_system, LoggerChannelInterface $logger) {
    $custom_path = $config->get('apigee_edge.auth')->get('oauth_token_storage_location');
    $this->tokenFilePath = empty($custom_path) ? static::DEFAULT_DIRECTORY : rtrim(trim($custom_path), " \\/");
    $this->tokenFilePath .= '/oauth.dat';
    $this->fileSystem = $file_system;
    $this->logger = $logger;
  }

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
    return $token_data['scope'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getExpires(): int {
    $token_data = $this->getTokenData();
    return $token_data['expires'] ?? time() - 1;
  }

  /**
   * {@inheritdoc}
   */
  public function hasExpired(): bool {
    $expires = $this->getExpires();
    return !($expires - $this->leeway > time());
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
    if (isset($data['expires_in'])) {
      $data['expires'] = $data['expires_in'] + time();
    }
    // Do not save the expires_in data to the storage.
    unset($data['expires_in']);

    try {
      $this->checkRequirements();
      // Write the obfuscated token data to a private file.
      $this->fileSystem->saveData(base64_encode(Json::encode($data)), $this->tokenFilePath, FileSystemInterface::EXISTS_REPLACE);
    }
    catch (FileException $e) {
      $this->logger->critical('Error saving OAuth token file.');
    }
    catch (OauthTokenStorageException $exception) {
      $this->logger->critical('OAuth token file storage: %error.', ['%error' => $exception->getMessage()]);
    }
    finally {
      // Even if an error occurs here token data can be still served from the
      // internal cache in this page request.
      $this->tokenData = $data;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): void {
    if ($this->isTokenFileInPrivateFileSystem() && !$this->isPrivateFileSystemConfigured()) {
      throw new OauthTokenStorageException('Unable to save token data to private filesystem because it has not been configured yet.');
    }
    // Gets the file directory so we can make sure it exists.
    $token_directory = $this->fileSystem->dirname($this->tokenFilePath);
    if (!$this->fileSystem->prepareDirectory($token_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new OauthTokenStorageException("Unable to set up {$token_directory} directory for token file.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeToken(): void {
    // Do not try to remove token from the token file if private filesystem
    // has not been configured yet. Also, do not create the token file
    // if it has not existed yet.
    if ($this->isTokenFileInPrivateFileSystem() && (!$this->isPrivateFileSystemConfigured() || ($this->isPrivateFileSystemConfigured() && !file_exists($this->tokenFilePath)))) {
      $this->tokenData = [];
      return;
    }
    else {
      $this->saveToken([]);
    }
  }

  /**
   * Removes the file in which the OAuth token data is stored.
   */
  public function removeTokenFile(): void {
    if (($this->isTokenFileInPrivateFileSystem() && !$this->isPrivateFileSystemConfigured()) || !file_exists($this->tokenFilePath)) {
      // Do not try to delete the file if private filesystem has not been
      // configured because in that cause "private://" scheme is not
      // registered. Also do nothing if token file does not exist.
      return;
    }
    try {
      $this->fileSystem->delete($this->tokenFilePath);
    }
    catch (FileException $e) {
      // Do nothing.
    }
  }

  /**
   * Gets the token data from the cache or the file.
   *
   * @param bool $reset
   *   Whether or not to reload the token data.
   *
   * @return array
   *   The token data from the internal cache or the token file. Returned array
   *   could be empty!
   */
  private function getTokenData(bool $reset = FALSE): array {
    // Load from storage if the cached value is empty.
    if ($reset || empty($this->tokenData)) {
      $this->tokenData = $this->getFromStorage();
    }

    return $this->tokenData;
  }

  /**
   * Reads the token data from the file.
   *
   * @return array
   *   The token data from the file or an empty array if file does not exist.
   */
  private function getFromStorage(): array {
    $data = [];
    // Get the token data from the file store.
    if (file_exists($this->tokenFilePath) && ($raw_data = file_get_contents($this->tokenFilePath))) {
      $data = Json::decode(base64_decode($raw_data));
    }
    return is_array($data) ? $data : [];
  }

  /**
   * Checks whether the token file's location in the private filesystem.
   *
   * @return bool
   *   True if the token file's location is in the private filesystem, false
   *   otherwise.
   */
  private function isTokenFileInPrivateFileSystem(): bool {
    return strpos($this->tokenFilePath, 'private://') === 0;
  }

  /**
   * Checks whether the private filesystem is configured.
   *
   * @return bool
   *   True if configured, FALSE otherwise.
   */
  private function isPrivateFileSystemConfigured(): bool {
    return (bool) $this->fileSystem->realpath('private://');
  }

}
