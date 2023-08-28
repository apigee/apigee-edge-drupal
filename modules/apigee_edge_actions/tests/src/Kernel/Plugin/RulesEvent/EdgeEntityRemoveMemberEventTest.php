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

use Drupal\rules\Context\ContextConfig;
use Drupal\Tests\apigee_edge_actions\Kernel\ApigeeEdgeActionsRulesKernelTestBase;

/**
 * Tests Edge entity remove_member event.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 * @group apigee_edge_actions
 * @group apigee_edge_actions_kernel
 */
class EdgeEntityRemoveMemberEventTest extends ApigeeEdgeActionsRulesKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'apigee_edge_actions',
    'apigee_edge',
    'apigee_edge_teams',
    'apigee_mock_api_client',
    'dblog',
    'key',
    'options',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('team_member_role');

    $this->addOrganizationMatchedResponse();

    // Setting teams cache to 900 to make sure null value is not returned in getCacheMaxAge().
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('apigee_edge_teams.team_settings');

    if (NULL === $config->get('cache_expiration')) {
      $config->set('cache_expiration', 900);
      $config->save(TRUE);
    }
  }

  /**
   * Tests add_member events for Edge entities.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\rules\Exception\LogicException
   */
  public function testEvent() {
    // Create an remove_member rule.
    $rule = $this->expressionManager->createRule();
    $rule->addAction('apigee_edge_actions_log_message',
      ContextConfig::create()
        ->setValue('message', "Member {{ member.first_name }} was removed from team {{ team.displayName }}.")
        ->process('message', 'rules_tokens')
    );

    $config_entity = $this->storage->create([
      'id' => 'app_remove_member_rule',
      'events' => [['event_name' => 'apigee_edge_actions_entity_remove_member:team']],
      'expression' => $rule->getConfiguration(),
    ]);
    $config_entity->save();

    // Create a new team.
    $team = $this->createTeam();

    $team_membership_manager = $this->container->get('apigee_edge_teams.team_membership_manager');

    // Add team member.
    $this->addUserToTeam($team, $this->account);

    // Remove team member.
    $this->stack->queueMockResponse('no_content');
    $team_membership_manager->removeMembers($team->id(), [
      $this->account->getEmail(),
    ]);

    $this->assertLogsContains("Event apigee_edge_actions_entity_remove_member:team was dispatched.");
    $this->assertLogsContains("Member {$this->account->first_name->value} was removed from team {$team->getDisplayName()}.");
  }

}
