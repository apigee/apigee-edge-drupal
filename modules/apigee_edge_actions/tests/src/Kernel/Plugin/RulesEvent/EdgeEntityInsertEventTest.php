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
 * Tests Edge entity insert event.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 * @group apigee_edge_actions
 * @group apigee_edge_actions_kernel
 */
class EdgeEntityInsertEventTest extends ApigeeEdgeActionsRulesKernelTestBase {

  /**
   * Tests insert events for Edge entities.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\rules\Exception\LogicException
   */
  public function testEvent() {
    // Create an insert rule.
    $rule = $this->expressionManager->createRule();
    $rule->addAction('apigee_edge_actions_log_message',
      ContextConfig::create()
        ->setValue('message', "App {{ developer_app.name }} was created by {{ developer.first_name }}.")
        ->process('message', 'rules_tokens')
    );

    $config_entity = $this->storage->create([
      'id' => 'app_insert_rule',
      'events' => [['event_name' => 'apigee_edge_actions_entity_insert:developer_app']],
      'expression' => $rule->getConfiguration(),
    ]);
    $config_entity->save();

    // Insert an entity.
    $entity = $this->createDeveloperApp();

    $this->assertLogsContains("Event apigee_edge_actions_entity_insert:developer_app was dispatched.");
    $this->assertLogsContains("App {$entity->getName()} was created by {$this->account->first_name->value}.");
  }

}
