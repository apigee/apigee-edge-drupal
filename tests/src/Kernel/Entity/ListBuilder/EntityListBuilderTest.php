<?php

/**
 * Copyright 2020 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\Tests\apigee_edge\Kernel\Entity\ListBuilder;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests for EntityListBuilder.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class EntityListBuilderTest extends KernelTestBase {

  use ApigeeMockApiClientHelperTrait, UserCreationTrait;

  /**
   * Indicates this test class is mock API client ready.
   *
   * @var bool
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * The entity type to test.
   */
  const ENTITY_TYPE = 'developer_app';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'apigee_edge',
    'apigee_mock_api_client',
    'key',
    'user',
    'options'
  ];

  /**
   * The user account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $account;

  /**
   * A DeveloperApp entity.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $app;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['apigee_edge']);

    $this->apigeeTestHelperSetup();

    $this->addOrganizationMatchedResponse();

    $this->account = User::create([
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => $this->randomMachineName(),
      'first_name' => $this->getRandomGenerator()->word(16),
      'last_name' => $this->getRandomGenerator()->word(16),
    ]);
    $this->account->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
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

      if ($this->app) {
        $this->app->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }

    parent::tearDown();
  }

  /**
   * Tests display settings for list builder.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testDisplaySettings() {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->app = $this->createDeveloperApp();
    $this->stack->queueMockResponse([
      'get_developer_apps' => [
        'apps' => [$this->app]
      ],
    ]);

    // Using default.
    $build = $entity_type_manager->getListBuilder(static::ENTITY_TYPE)->render();
    static::assertTrue(isset($build['table']));

    // Add view mode.
    EntityViewMode::create([
      'id' => static::ENTITY_TYPE . '.foo',
      'targetEntityType' => static::ENTITY_TYPE,
      'label' => 'Foo',
      'status' => TRUE,
    ])->save();

    $config = $this->config('apigee_edge.display_settings.' . static::ENTITY_TYPE);
    $config->set('display_type', 'view_mode')
      ->set('view_mode', 'foo')
      ->save();

    // Using view mode.
    $build = $entity_type_manager->getListBuilder(static::ENTITY_TYPE)->render();
    static::assertSame('apigee_entity_list', $build['#type']);
    static::assertSame('foo', $build['#view_mode']);
  }

}
