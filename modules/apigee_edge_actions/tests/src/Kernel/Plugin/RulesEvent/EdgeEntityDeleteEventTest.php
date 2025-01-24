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
use Drupal\rules\Context\ContextConfig;

/**
 * Tests Edge entity delete event.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 * @group apigee_edge_actions
 * @group apigee_edge_actions_kernel
 */
class EdgeEntityDeleteEventTest extends ApigeeEdgeActionsRulesKernelTestBase {

  /**
   * Tests delete events for Edge entities.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\rules\Exception\LogicException
   */
  public function testEvent() {
    // Create a delete rule.
    $rule = $this->expressionManager->createRule();
    $rule->addAction('apigee_edge_actions_log_message',
      ContextConfig::create()
        ->setValue('message', "App {{ developer_app.name }} was deleted.")
        ->process('message', 'rules_tokens')
    );

    $config_entity = $this->storage->create([
      'id' => 'app_delete_rule',
      'events' => [['event_name' => 'apigee_edge_actions_entity_delete:developer_app']],
      'expression' => $rule->getConfiguration(),
    ]);
    $config_entity->save();

    // Insert and delete entity.
    $entity = $this->createDeveloperApp();
    $this->queueDeveloperAppResponse($entity);
    $entity->delete();

    $this->assertLogsContains("Event apigee_edge_actions_entity_delete:developer_app was dispatched.");
    $this->assertLogsContains("App {$entity->getName()} was deleted.");
  }

}
