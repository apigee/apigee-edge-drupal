<?php

/*
 * Copyright 2020 Google Inc.
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

use Apigee\MockClient\GuzzleHttp\MockHandler;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\HandlerStack;

/**
 * Tests the testing framework for testing offline.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class MockIntegrationToggleKernelTest extends KernelTestBase {

  /**
   * Indicates this test class is mock API client ready.
   *
   * @var bool
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'key',
    'file',
    'entity',
    'syslog',
    'apigee_edge',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['apigee_edge']);

    // Prepare to create a user.
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Tests that the sdk client will use the mock handler stack.
   */
  public function testIntegrationToggleOff() {
    $integration_enabled = getenv('APIGEE_INTEGRATION_ENABLE');
    putenv('APIGEE_INTEGRATION_ENABLE=0');

    $this->enableModules(['apigee_mock_api_client']);

    // @todo getConfig() is deprecated and will be removed in guzzlehttp/guzzle:8.0
    // @phpstan-ignore-next-line
    $handler = $this->container
      ->get('apigee_mock_api_client.mock_http_client_factory')
      ->fromOptions([])
      ->getConfig('handler');

    self::assertInstanceOf(MockHandler::class, $handler);

    putenv('APIGEE_INTEGRATION_ENABLE=' . $integration_enabled ? 1 : 0);
  }

  /**
   * Tests that the sdk client will be unaffected while integration is enabled.
   */
  public function testIntegrationToggleOn() {
    $integration_enabled = getenv('APIGEE_INTEGRATION_ENABLE');
    putenv('APIGEE_INTEGRATION_ENABLE=1');

    $this->enableModules(['apigee_mock_api_client']);

    // @todo getConfig() is deprecated and will be removed in guzzlehttp/guzzle:8.0
    // @phpstan-ignore-next-line
    $handler = $this->container
      ->get('apigee_mock_api_client.mock_http_client_factory')
      ->fromOptions([])
      ->getConfig('handler');

    self::assertInstanceOf(HandlerStack::class, $handler);

    putenv('APIGEE_INTEGRATION_ENABLE=' . $integration_enabled ? 1 : 0);
  }

}
