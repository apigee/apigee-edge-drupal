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

namespace Drupal\Tests\apigee_edge_actions\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;
use Drupal\Tests\rules\Kernel\RulesKernelTestBase;
use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\dblog\Controller\DbLogController;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a base class for testing Edge entity events.
 */
class ApigeeEdgeActionsRulesKernelTestBase extends RulesKernelTestBase {

  use ApigeeMockApiClientHelperTrait {
    apigeeTestHelperSetup as baseSetUp;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'apigee_edge_actions',
    'apigee_edge_actions_debug',
    'apigee_edge',
    'apigee_mock_api_client',
    'dblog',
    'key',
    'options',
  ];

  /**
   * The entity storage for Rules config entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The user account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Skipping the test if instance type is Public.
    $instance_type = getenv('APIGEE_EDGE_INSTANCE_TYPE');
    if (!empty($instance_type) && $instance_type === EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID) {
      $this->markTestSkipped('This test suite is expecting a PUBLIC instance type.');
    }
    parent::setUp();

    $this->storage = $this->container->get('entity_type.manager')->getStorage('rules_reaction_rule');

    $this->installConfig(['apigee_edge']);
    $this->installEntitySchema('user');
    $this->installSchema('dblog', ['watchdog']);
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);

    $this->baseSetUp();

    /** @var \Drupal\user\UserInterface $account */
    $this->account = User::create([
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => $this->randomMachineName(),
      'first_name' => $this->getRandomGenerator()->word(16),
      'last_name' => $this->getRandomGenerator()->word(16),
    ]);
    $this->queueDeveloperResponse($this->account, Response::HTTP_CREATED);
    $this->account->save();
  }

  /**
   * Helper to assert logs.
   *
   * @param string $message
   *   The message to assert in the logs.
   * @param string $type
   *   The type for the log.
   */
  protected function assertLogsContains(string $message, $type = 'apigee_edge_actions') {
    $logs = Database::getConnection()->select('watchdog', 'wd')
      ->fields('wd', ['message', 'variables'])
      ->condition('type', $type)
      ->execute()
      ->fetchAll();

    $controller = DbLogController::create($this->container);
    $messages = array_map(function ($log) use ($controller) {
      return (string) $controller->formatMessage($log);
    }, $logs);

    $this->assertContains($message, $messages);
  }

}
