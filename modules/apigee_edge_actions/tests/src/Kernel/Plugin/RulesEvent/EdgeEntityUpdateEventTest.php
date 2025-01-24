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
 * Tests Edge entity update event.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 * @group apigee_edge_actions
 * @group apigee_edge_actions_kernel
 */
class EdgeEntityUpdateEventTest extends ApigeeEdgeActionsRulesKernelTestBase {

  /**
   * Tests update events for Edge entities.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\rules\Exception\LogicException
   */
  public function testEvent() {
    // Create an update rule.
    $rule = $this->expressionManager->createRule();
    $rule->addAction('apigee_edge_actions_log_message',
      ContextConfig::create()
        ->setValue('message', "App {{ developer_app_unchanged.displayName }} was renamed to {{ developer_app.displayName }}.")
        ->process('message', 'rules_tokens')
    );

    $config_entity = $this->storage->create([
      'id' => 'app_update_rule',
      'events' => [['event_name' => 'apigee_edge_actions_entity_update:developer_app']],
      'expression' => $rule->getConfiguration(),
    ]);
    $config_entity->save();

    // Add matched organization response so it returns the org whenever called.
    $this->addOrganizationMatchedResponse();

    // Insert and update entity.
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $entity */
    $entity = $this->createDeveloperApp();
    $original_name = $entity->getDisplayName();
    $new_name = $this->randomGenerator->name();
    $this->queueDeveloperAppResponse($entity);
    $entity->setDisplayName($new_name);
    $this->queueDeveloperAppResponse($entity);
    $entity->save();

    $this->assertLogsContains("Event apigee_edge_actions_entity_update:developer_app was dispatched.");
    $this->assertLogsContains("App $original_name was renamed to $new_name.");
  }

}
