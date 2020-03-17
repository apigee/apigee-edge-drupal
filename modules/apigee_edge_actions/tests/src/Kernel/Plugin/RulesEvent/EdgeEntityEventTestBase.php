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

namespace Drupal\Tests\apigee_edge_actions\Kernel\Plugin\RulesEvent;

use Apigee\Edge\Api\Management\Entity\App;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Entity\EdgeEntityInterface;
use Drupal\apigee_edge_teams\Entity\Team;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\Core\Database\Database;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;
use Drupal\Tests\rules\Kernel\RulesKernelTestBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a base class for testing Edge entity events.
 */
class EdgeEntityEventTestBase extends RulesKernelTestBase {

  use ApigeeMockApiClientHelperTrait {
    apigeeTestHelperSetup as baseSetUp;
  }

  /**
   * {@inheritdoc}
   */
  public static $modules = [
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
  protected function setUp() {
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
    $this->account->save();
    $this->queueDeveloperResponse($this->account, Response::HTTP_CREATED);
  }

  /**
   * Helper to create a DeveloperApp entity.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperAppInterface
   *   A DeveloperApp entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createDeveloperApp(): DeveloperAppInterface {
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $entity */
    $entity = DeveloperApp::create([
      'appId' => 1,
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'displayName' => $this->randomMachineName(),
    ]);
    $entity->setOwner($this->account);
    $this->queueDeveloperAppResponse($entity);
    $entity->save();

    return $entity;
  }

  /**
   * Helper to create a Team entity.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamInterface
   *   A Team entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createTeam(): TeamInterface {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $team */
    $team = Team::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomGenerator->name(),
    ]);
    $this->queueCompanyResponse($team->decorated());
    $this->queueDeveloperResponse($this->account);
    $team->save();

    return $team;
  }

  /**
   * Helper to add Edge entity response to stack.
   *
   * @param \Drupal\apigee_edge\Entity\EdgeEntityInterface $entity
   *   The Edge entity.
   */
  protected function queueDeveloperAppResponse(EdgeEntityInterface $entity) {
    $this->stack->queueMockResponse([
      'get_developer_app' => [
        'app' => $entity,
      ],
    ]);
  }

  /**
   * Helper to assert logs.
   *
   * @param string $message
   *   The message to assert in the logs.
   */
  protected function assertLogsContains(string $message) {
    $logs = Database::getConnection()->select('watchdog', 'wd')
      ->fields('wd', ['message'])
      ->condition('type', 'apigee_edge_actions')
      ->execute()
      ->fetchCol();

    $this->assertContains($message, $logs);
  }

}
