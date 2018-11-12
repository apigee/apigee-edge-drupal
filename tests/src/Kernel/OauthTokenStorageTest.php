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

use Drupal\apigee_edge\OauthTokenFileStorage;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DrupalKernel;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * OAuth cache storage tests.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class OauthTokenStorageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'apigee_edge',
    'key',
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

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
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

    // Add file_private_path setting.
    $private_directory = DrupalKernel::findSitePath(Request::create('/')) . '/private';
    $this->setSetting('file_private_path', $private_directory);
    // Make sure the directory exists.
    file_prepare_directory($private_directory, FILE_CREATE_DIRECTORY);

    static::assertDirectoryExists($private_directory);
  }

  /**
   * Test service instantiation.
   */
  public function testGetTokenStorage() {
    static::assertInstanceOf(OauthTokenFileStorage::class, $this->token_storage);
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
    $stored_token = unserialize(base64_decode(file_get_contents(OauthTokenFileStorage::OAUTH_TOKEN_PATH)));

    // Test token values.
    static::assertSame($this->token_data['access_token'], $stored_token['access_token']);
    static::assertSame($this->token_data['token_type'], $stored_token['token_type']);
    static::assertSame($this->token_data['refresh_token'], $stored_token['refresh_token']);
    static::assertSame($this->token_data['scope'], $stored_token['scope']);
    // The difference in the timestamp should be 1 or 0 seconds.
    static::assertLessThan(2, abs($this->token_data['expires_in'] + $current_time - $stored_token['expires']));
  }

  /**
   * Test the get and has methods of the cache storage.
   */
  public function testGetters() {
    // Will use this to test expire.
    $current_time = time();
    // Save the token.
    $this->token_storage->saveToken($this->token_data);

    static::assertSame($this->token_data['access_token'], $this->token_storage->getAccessToken());
    static::assertSame($this->token_data['token_type'], $this->token_storage->getTokenType());
    static::assertSame($this->token_data['refresh_token'], $this->token_storage->getRefreshToken());
    static::assertSame($this->token_data['scope'], $this->token_storage->getScope());
    // The difference in the timestamp should be 1 or 0 seconds.
    static::assertLessThan(2, abs($this->token_data['expires_in'] + $current_time - $this->token_storage->getExpires()));

    // The token should still be valid for 5 minutes.
    static::assertFalse($this->token_storage->hasExpired());

    // Expire token.
    $this->token_storage->markExpired();

    // The token should still be valid for 5 minutes.
    static::assertTrue($this->token_storage->hasExpired());
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

}
