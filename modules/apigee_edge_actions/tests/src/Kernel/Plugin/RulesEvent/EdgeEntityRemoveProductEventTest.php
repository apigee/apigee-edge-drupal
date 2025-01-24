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

use Drupal\Tests\apigee_edge_actions\Kernel\ApigeeEdgeActionsRulesKernelTestBase;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\rules\Context\ContextConfig;

/**
 * Tests Edge entity remove_product event.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 * @group apigee_edge_actions
 * @group apigee_edge_actions_kernel
 */
class EdgeEntityRemoveProductEventTest extends ApigeeEdgeActionsRulesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'apigee_edge_actions',
    'apigee_edge_actions_debug',
    'apigee_edge',
    'apigee_edge_teams',
    'apigee_mock_api_client',
    'dblog',
    'key',
    'options',
  ];

  /**
   * Tests add_member events for Edge entities.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\rules\Exception\LogicException
   */
  public function testEvent() {
    // Create an insert rule.
    $rule = $this->expressionManager->createRule();
    $rule->addAction('apigee_edge_actions_log_message',
      ContextConfig::create()
        ->setValue('message', "Product {{ api_product_name }} was removed from app {{ developer_app.name }}.")
        ->process('message', 'rules_tokens')
    );

    $config_entity = $this->storage->create([
      'id' => 'app_insert_rule',
      'events' => [['event_name' => 'apigee_edge_actions_entity_remove_product:developer_app']],
      'expression' => $rule->getConfiguration(),
    ]);
    $config_entity->save();

    /** @var \Drupal\apigee_edge\Entity\AppInterface $developer_app */
    $developer_app = $this->createDeveloperApp();

    $api_product = ApiProduct::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->getRandomGenerator()->word(16),
      'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
    ]);

    /** @var \Drupal\apigee_edge\Entity\ApiProduct $api_product */
    $this->stack->queueMockResponse([
      'api_product' => [
        'product' => $api_product,
      ],
    ]);

    $api_product->save();

    $this->stack->queueMockResponse([
      'api_product' => [
        'product' => $api_product,
      ],
    ]);
    $this->queueDeveloperResponse($this->account);
    $this->queueDeveloperAppResponse($developer_app);

    /** @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerFactoryInterface $credential_factory */
    $credential_factory = \Drupal::service('apigee_edge.controller.developer_app_credential_factory');
    /** @var \Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface $app_credential_controller */
    $app_credential_controller = $credential_factory->developerAppCredentialController($this->account->uuid(), $developer_app->getName());
    $consumer_key = $this->randomString();
    $app_credential_controller->addProducts($consumer_key, [$api_product->id()]);

    $this->stack->queueMockResponse([
      'api_product' => [
        'product' => $api_product,
      ],
    ]);
    $app_credential_controller->deleteApiProduct($consumer_key, $api_product->id());

    $this->assertLogsContains("Event apigee_edge_actions_entity_remove_product:developer_app was dispatched.");
    $this->assertLogsContains("Product {$api_product->getName()} was removed from app {$developer_app->getName()}.");
  }

}
