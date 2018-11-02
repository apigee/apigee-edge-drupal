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

namespace Drupal\Tests\apigee_edge\Kernel;

use Drupal\apigee_edge\OauthTokenCacheStorage;
use Drupal\KernelTests\KernelTestBase;


/**
 * OAuth cache storage tests.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class OauthTokenCacheStorageTest extends KernelTestBase {
  protected static $modules = [
    'apigee_edge',
    'key'
  ];

  /**
   * Token storage.
   *
   * @var \Apigee\Edge\HttpClient\Plugin\Authentication\OauthTokenStorageInterface
   */
  protected $token_storage;

  /**
   * Test token data.
   *
   * @var array
   */
  protected $token_data;

  public function setUp() {
    parent::setUp();

    $this->token_storage = $this->container->get('apigee_edge.token_storage');
    // Create sample token data.
    $this->token_data = [
      'access_token' => strtolower($this->randomMachineName(32)),
      'token_type' => 'bearer',
      'expires_in' => 300,
      'refresh_token' => strtolower($this->randomMachineName(32)),
      'scope' => 'create',
    ];
  }

  /**
   * Test service instantiation.
   */
  public function testGetTokenStorage() {
    static::assertInstanceOf(OauthTokenCacheStorage::class, $this->token_storage);
  }

  /**
   * Test that saving a token produces the expected cache result.
   *
   * @throws \Exception
   */
  public function testSaveToken() {
    // Will use this to test expire.
    $current_time = time();
    // Save the token.
    $this->token_storage->saveToken($this->token_data);

    // Load raw token data.
    $cached_token = $this->container->get('cache.apigee_edge')->get(OauthTokenCacheStorage::OAUTH_TOKEN_CID);

    // Test token values.
    static::assertSame($this->token_data['access_token'], $cached_token->data['access_token']);
    static::assertSame($this->token_data['token_type'], $cached_token->data['token_type']);
    static::assertSame($this->token_data['refresh_token'], $cached_token->data['refresh_token']);
    static::assertSame($this->token_data['scope'], $cached_token->data['scope']);
    // The difference in the timestamp should be 1 or 0 seconds.
    static::assertLessThan(2, abs($this->token_data['expires_in']+$current_time - $cached_token->expire));
  }

  public function testGetters() {
    // Will use this to test expire.
    $current_time = time();
    // Save the token.
    $this->token_storage->saveToken($this->token_data);

    static::assertSame($this->token_data['access_token'],   $this->token_storage->getAccessToken());
    static::assertSame($this->token_data['token_type'],     $this->token_storage->getTokenType());
    static::assertSame($this->token_data['refresh_token'],  $this->token_storage->getRefreshToken());
    static::assertSame($this->token_data['scope'],          $this->token_storage->getScope());
    // The difference in the timestamp should be 1 or 0 seconds.
    static::assertLessThan(2, abs($this->token_data['expires_in']+$current_time - $this->token_storage->getExpires()));

    // The token should still be valid for 5 minutes.
    static::assertFalse($this->token_storage->hasExpired());

    // Expire token.
    $this->token_storage->markExpired();

    // The token should still be valid for 5 minutes.
    static::assertTrue($this->token_storage->hasExpired());

  }
}
