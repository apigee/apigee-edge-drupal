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
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Storing and returning OAuth access token data.
 */
class OauthTokenCacheStorage implements OauthTokenStorageInterface, ContainerInjectionInterface {

  /**
   * The access token cache ID.
   */
  const OAUTH_TOKEN_CID = 'apigee_edge.oauth_token';

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
   * The cache that is used to store the oauth keys.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * OauthTokenCacheStorage constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   */
  public function __construct(CacheBackendInterface $cache) {
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.apigee_edge')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(): ?string {
    return $this->getFromCache()['access_token'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenType(): ?string {
    return $this->getFromCache()['token_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRefreshToken(): ?string {
    return $this->getFromCache()['refresh_token'];
  }

  /**
   * {@inheritdoc}
   */
  public function getScope(): string {
    return $this->getFromCache()['scope'];
  }

  /**
   * {@inheritdoc}
   */
  public function getExpires(): int {
    $cache = $this->cache->get(static::OAUTH_TOKEN_CID, TRUE);
    return $cache->expire ?? -1;
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
    $this->removeToken();
  }

  /**
   * {@inheritdoc}
   */
  public function saveToken(array $data): void {
    // Calculate the cache expiration.
    $expires = $data['expires_in'] + time();
    // Remove the expires_in data.
    unset($data['expires_in']);

    // Save token data.
    $this->cache->set(static::OAUTH_TOKEN_CID, $data, $expires);
  }

  /**
   * {@inheritdoc}
   */
  public function removeToken(): void {
    $this->cache->delete(static::OAUTH_TOKEN_CID);
  }

  protected function getFromCache(): ?array {
    // Get cache data.
    $cached = $this->cache->get(static::OAUTH_TOKEN_CID);
    return $cached->data ?? [
      'access_token' => NULL,
      'token_type' => NULL,
      'expires_in' => NULL,
      'refresh_token' => NULL,
      'scope' => '',
    ];
  }
}
