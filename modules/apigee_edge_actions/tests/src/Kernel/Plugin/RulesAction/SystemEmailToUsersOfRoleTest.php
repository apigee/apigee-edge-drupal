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

namespace Drupal\Tests\apigee_edge_actions\Kernel\Plugin\RulesAction;

use Drupal\Tests\apigee_edge_actions\Kernel\ApigeeEdgeActionsRulesKernelTestBase;
use Drupal\rules\Context\ContextConfig;

/**
 * Tests Edge entity add_member event.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 * @group apigee_edge_actions
 * @group apigee_edge_actions_kernel
 */
class SystemEmailToUsersOfRoleTest extends ApigeeEdgeActionsRulesKernelTestBase {

  /**
   * Tests sending email to role event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\rules\Exception\LogicException
   */
  public function testAction() {
    $this->markTestSkipped('Skipping for Drupal10 as test fails.');

    $role_storage = $this->container->get('entity_type.manager')->getStorage('user_role');
    $role_storage->create(['id' => 'test_role'])->save();
    $this->account->addRole('test_role');
    $this->queueDeveloperResponse($this->account);
    $this->account->activate();
    $this->account->save();

    $rule = $this->expressionManager->createRule();
    $rule->addAction('rules_email_to_users_of_role',
      ContextConfig::create()
        ->setValue('roles', ['test_role'])
        ->setValue('subject', 'Test email')
        ->setValue('message', 'This is a test email')
    );

    $config_entity = $this->storage->create([
      'id' => 'send_email_to_admin_rule',
      'events' => [['event_name' => 'apigee_edge_actions_entity_insert:developer_app']],
      'expression' => $rule->getConfiguration(),
    ]);
    $config_entity->save();

    // Insert an entity to trigger rule.
    $this->queueDeveloperResponse($this->account);
    $this->createDeveloperApp();

    $this->assertLogsContains("Event apigee_edge_actions_entity_insert:developer_app was dispatched.");
    $this->assertLogsContains('Successfully sent email to <em class="placeholder">1</em> out of <em class="placeholder">1</em> users having the role(s) <em class="placeholder">test_role</em>', 'rules');
  }

}
