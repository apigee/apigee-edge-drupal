<?php

/**
 * Copyright 2023 Google Inc.
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

namespace Drupal\Tests\apigee_edge\Kernel\ApigeeX;

use Drupal\KernelTests\KernelTestBase;
use Drupal\apigee_edge\OauthTokenFileStorage;
use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;

/**
 * Base class for kernel tests.
 */
abstract class ApigeeEdgeKernelTestBase extends KernelTestBase {

  /**
   * Test token data.
   *
   * @var array
   */
  private $testTokenData = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Skipping the test if instance type is Public.
    $instance_type = getenv('APIGEE_EDGE_INSTANCE_TYPE');
    if (!empty($instance_type) && $instance_type === EdgeKeyTypeInterface::INSTANCE_TYPE_PUBLIC) {
      $this->markTestSkipped('This test suite is expecting a HYBRID instance type.');
    }
    parent::setUp();
  }

  /**
   * Stores pre-configured token storage service for testing.
   */
  protected function storeToken() {
    // Storing the token for Apigeex Hybrid Org.
    $this->testTokenData = [
      'access_token' => mb_strtolower($this->randomMachineName(32)),
      'token_type' => 'bearer',
      'expires_in' => 300,
      'refresh_token' => mb_strtolower($this->randomMachineName(32)),
      'scope' => 'create',
    ];
    $storage = $this->tokenStorage();

    // Save the token.
    $storage->saveToken($this->testTokenData);
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
    $config->set('oauth_token_storage_location', OauthTokenFileStorage::DEFAULT_DIRECTORY)->save();
    if ($rebuild) {
      $this->container->get('kernel')->rebuildContainer();
    }
    return $this->container->get('apigee_edge.authentication.oauth_token_storage');
  }

}
