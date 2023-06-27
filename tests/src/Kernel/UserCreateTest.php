<?php

/**
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

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;
use Drupal\user\Entity\User;

/**
 * Test create operations for User entity type.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class UserCreateTest extends KernelTestBase {

  use ApigeeMockApiClientHelperTrait;

  /**
   * A Drupal user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'apigee_edge',
    'key',
    'apigee_mock_api_client',
  ];

  /**
   * Indicates this test class is mock API client ready.
   *
   * @var bool
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('user');

    $this->apigeeTestHelperSetup();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->stack->reset();
    try {
      if ($this->account) {
        $this->queueDeveloperResponse($this->account);
        $developer = \Drupal::entityTypeManager()
          ->getStorage('developer')
          ->create([
            'email' => $this->account->getEmail(),
          ]);
        $developer->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }

    parent::tearDown();
  }

  /**
   * Test user create.
   */
  public function testUserCreate() {
    $this->account = User::create([
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => $this->randomMachineName(),
      'first_name' => $this->randomMachineName(64),
      'last_name' => $this->randomMachineName(64),
    ]);

    $this->queueDeveloperResponse($this->account, 201);

    $this->assertEquals(SAVED_NEW, $this->account->save());
  }

}
