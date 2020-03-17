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

/**
 * Tests Edge entity remove_member event.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 * @group apigee_edge_actions
 * @group apigee_edge_actions_kernel
 */
class EdgeEntityRemoveMemberEventTest extends EdgeEntityEventTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
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
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('team_member_role');
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

    // Add team member.
    $this->queueCompanyResponse($team->decorated());
    $this->queueDeveloperResponse($this->account);
    $team_membership_manager = $this->container->get('apigee_edge_teams.team_membership_manager');
    $team_membership_manager->addMembers($team->id(), [
      $this->account->getEmail(),
    ]);

    // Remove team member.
    $team_membership_manager->removeMembers($team->id(), [
      $this->account->getEmail(),
    ]);

    $this->assertLogsContains("Event apigee_edge_actions_entity_remove_member:team was dispatched.");
    $this->assertLogsContains("Member {$this->account->first_name->value} was removed from team {$team->getDisplayName()}.");
  }

}
