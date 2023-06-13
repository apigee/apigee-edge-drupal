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

use Drupal\apigee_edge\Exception\OauthTokenStorageException;
use Drupal\apigee_edge\OauthTokenFileStorage;
use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileSystemInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * OAuth cache storage tests.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class OauthTokenFileStorageTest extends KernelTestBase {

  /**
   * Indicates this test class is mock API client ready.
   *
   * @var bool
   */
  protected static $mock_api_client_ready = TRUE;

  private const CUSTOM_TOKEN_DIR = 'oauth/token/dir';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'apigee_edge',
    'key',
  ];

  /**
   * Test token data.
   *
   * @var array
   */
  private $testTokenData = [];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function setUp(): void {
    parent::setUp();

    $this->testTokenData = [
      'access_token' => mb_strtolower($this->randomMachineName(32)),
      'token_type' => 'bearer',
      'expires_in' => 300,
      'refresh_token' => mb_strtolower($this->randomMachineName(32)),
      'scope' => 'create',
    ];
  }

  /**
   * Returns a pre-configured token storage service for testing.
   *
   * @param bool $rebuild
   *   Enforces rebuild of the container and with the the token storage
   *   service.
   *
   * @return \Drupal\apigee_edge\OauthTokenFileStorage
   *   The configured and initialized OAuth file token storage service.
   *
   * @throws \Exception
   */
  private function tokenStorage(bool $rebuild = FALSE): OauthTokenFileStorage {
    $config = $this->config('apigee_edge.auth');
    $config->set('oauth_token_storage_location', $this->tokenDirectoryUri())->save();
    if ($rebuild) {
      $this->container->get('kernel')->rebuildContainer();
    }
    return $this->container->get('apigee_edge.authentication.oauth_token_storage');
  }

  /**
   * Returns the URI of the token directory.
   *
   * @return string
   *   Token directory URI.
   */
  private function tokenDirectoryUri(): string {
    return $this->vfsRoot->url() . '/' . static::CUSTOM_TOKEN_DIR;
  }

  /**
   * Returns the URI of the token file.
   *
   * @return string
   *   URI of the token file.
   */
  private function tokenFileUri(): string {
    return $this->tokenDirectoryUri() . '/oauth.dat';
  }

  /**
   * Validates checks in the storage.
   */
  public function testCheckRequirements() {
    /** @var \Drupal\apigee_edge\OauthTokenFileStorage $storage */
    $storage = $this->container->get('apigee_edge.authentication.oauth_token_storage');
    try {
      $storage->checkRequirements();
    }
    catch (OauthTokenStorageException $exception) {
      $this->assertEquals('Unable to save token data to private filesystem because it has not been configured yet.', $exception->getMessage());
    }
    // @see \Drupal\Core\StreamWrapper\LocalStream::getLocalPath()
    $this->setSetting('file_private_path', 'vfs://private');
    try {
      $storage->checkRequirements();
    }
    catch (OauthTokenStorageException $exception) {
      $this->assertEquals(sprintf('Unable to set up %s directory for token file.', OauthTokenFileStorage::DEFAULT_DIRECTORY), $exception->getMessage());
    }

    $storage = $this->tokenStorage(TRUE);
    // No exception should be thrown anymore.
    $storage->checkRequirements();
    $this->assertTrue(file_exists($this->vfsRoot->getChild(static::CUSTOM_TOKEN_DIR)->url()));
  }

  /**
   * Test that saving a token produces the expected file data.
   */
  public function testSaveToken() {
    $storage = $this->tokenStorage();
    // Will use this to test expire.
    $current_time = time();
    // Save the token.
    $storage->saveToken($this->testTokenData);

    // Load raw token data.
    $stored_token = Json::decode(base64_decode(file_get_contents($this->tokenFileUri())));

    // Test token values.
    $this->assertSame($this->testTokenData['access_token'], $stored_token['access_token']);
    $this->assertSame($this->testTokenData['token_type'], $stored_token['token_type']);
    $this->assertSame($this->testTokenData['refresh_token'], $stored_token['refresh_token']);
    $this->assertSame($this->testTokenData['scope'], $stored_token['scope']);
    // The difference in the timestamp should be 1 or 0 seconds.
    $this->assertLessThan(2, abs($this->testTokenData['expires_in'] + $current_time - $stored_token['expires']));
  }

  /**
   * Test the token storage is using static cache.
   */
  public function testStaticCaching() {
    $storage = $this->tokenStorage();
    // Save the token.
    $storage->saveToken($this->testTokenData);

    $access_token = $storage->getAccessToken();

    // Load raw token data.
    $stored_token = Json::decode(base64_decode(file_get_contents($this->tokenFileUri())));

    // Create a new access token and write it to file.
    $stored_token['access_token'] = mb_strtolower($this->randomMachineName(32));
    \Drupal::service('file_system')->saveData(
      base64_encode(Json::encode($stored_token)),
      $this->tokenFileUri(), FileSystemInterface::EXISTS_REPLACE);

    // Make sure the cached version is still returned.
    $this->assertSame($access_token, $storage->getAccessToken());
  }

  /**
   * Test the get and has methods of the cache storage.
   */
  public function testGetters() {
    $storage = $this->tokenStorage();
    // Will use this to test expire.
    $current_time = time();
    // Save the token.
    $storage->saveToken($this->testTokenData);

    $this->assertSame($this->testTokenData['access_token'], $storage->getAccessToken());
    $this->assertSame($this->testTokenData['token_type'], $storage->getTokenType());
    $this->assertSame($this->testTokenData['refresh_token'], $storage->getRefreshToken());
    $this->assertSame($this->testTokenData['scope'], $storage->getScope());
    // The difference in the timestamp should be 1 or 0 seconds.
    $this->assertLessThan(2, abs($this->testTokenData['expires_in'] + $current_time - $storage->getExpires()));

    // The token should still be valid for 5 minutes.
    $this->assertFalse($storage->hasExpired());

    // Expire token.
    $storage->markExpired();

    // The token should not be valid anymore.
    $this->assertTrue($storage->hasExpired());
  }

  /**
   * Test that the tokens are removed when cache is cleared.
   */
  public function testCacheClear() {
    $storage = $this->tokenStorage();
    // Save the token.
    $storage->saveToken($this->testTokenData);
    $this->assertNotEmpty($storage->getAccessToken());
    drupal_flush_all_caches();
    $this->assertEmpty($storage->getAccessToken());
  }

  /**
   * Tests OAuth token data file removal on module uninstall.
   */
  public function testTokenFileRemovalOnUninstall() {
    $storage = $this->tokenStorage();
    // Save the token.
    $storage->saveToken($this->testTokenData);
    $this->assertTrue(file_exists($this->tokenFileUri()), 'Oauth token data file should not exist after the module got uninstalled.');
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $installer */
    $installer = $this->container->get('module_installer');
    $installer->uninstall(['apigee_edge']);
    $this->assertFalse(file_exists($this->tokenFileUri()), 'Oauth token data file should not exist after the module got uninstalled.');
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    // The private:// scheme is the default scheme used by the token storage
    // therefore it has to be available but we are going to use vfs:// in tests.
    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

}
