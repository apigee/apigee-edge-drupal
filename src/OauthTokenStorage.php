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

use Apigee\Edge\Exception\OauthAuthenticationException;
use Apigee\Edge\HttpClient\Plugin\Authentication\OauthTokenStorageInterface;
use Drupal\apigee_edge\Plugin\EdgeOauthTokenKeyTypeInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\key\KeyInterface;

/**
 * Storing and returning OAuth access token data.
 */
class OauthTokenStorage implements OauthTokenStorageInterface {

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
   * The OAuth token key entity.
   *
   * @var \Drupal\key\KeyInterface
   */
  protected $key;

  /**
   * The key type of the OAuth token key entity.
   *
   * @var \Drupal\apigee_edge\Plugin\EdgeOauthTokenKeyTypeInterface
   */
  protected $keyType;

  /**
   * OauthTokenStorage constructor.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The OAuth token key entity.
   *
   * @throws \InvalidArgumentException
   *   An InvalidArgumentException is thrown if the key type
   *   does not implement EdgeOauthTokenKeyTypeInterface.
   */
  public function __construct(KeyInterface $key) {
    if (!($key->getKeyType() instanceof EdgeOauthTokenKeyTypeInterface)) {
      throw new \InvalidArgumentException("Type of {$key->id()} key does not implement EdgeOauthTokenKeyTypeInterface.");
    }

    $this->key = $key;
    $this->keyType = $key->getKeyType();
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(): ?string {
    return $this->keyType->getAccessToken($this->key);
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenType(): ?string {
    return $this->keyType->getTokenType($this->key);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpires(): int {
    return $this->keyType->getExpires($this->key) ?? 0;
  }

  /**
   * {@inheritdoc}
   */
  public function hasExpired(): bool {
    if ($this->getExpires() !== 0 && ($this->getExpires() - $this->leeway) > time()) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function markExpired(): void {
    $this->keyType->resetExpires($this->key);
  }

  /**
   * {@inheritdoc}
   */
  public function getRefreshToken(): ?string {
    return $this->keyType->getRefreshToken($this->key);
  }

  /**
   * {@inheritdoc}
   */
  public function getScope(): string {
    return $this->keyType->getScope($this->key);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Apigee\Edge\Exception\OauthAuthenticationException
   *   Thrown when the OAuth data cannot be saved.
   */
  public function saveToken(array $data): void {
    try {
      $data['expires'] = $data['expires_in'] + time();
      $this->key->setKeyValue($this->keyType->serialize($data));
      $this->key->save();
    }
    catch (EntityStorageException $exception) {
      throw new OauthAuthenticationException('Could not save the OAuth token data.');
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Apigee\Edge\Exception\OauthAuthenticationException
   *   Thrown when the OAuth data cannot be removed.
   */
  public function removeToken(): void {
    try {
      $this->key->setKeyValue($this->keyType->serialize([]));
      $this->key->save();
    }
    catch (EntityStorageException $exception) {
      throw new OauthAuthenticationException('Could not remove the stored OAuth token data.');
    }
  }

}
