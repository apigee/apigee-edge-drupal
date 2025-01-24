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

namespace Drupal\Tests\apigee_edge_teams\Kernel;

use Drupal\Tests\apigee_edge\Kernel\ApigeeEdgeKernelTestBase;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;
use Drupal\apigee_edge_teams\Entity\TeamInvitation;
use Drupal\apigee_edge_teams\Entity\TeamInvitationInterface;
use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;

/**
 * Tests team_invitation events.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 * @group apigee_edge_teams
 * @group apigee_edge_teams_kernel
 */
class TeamInvitationEventsTest extends ApigeeEdgeKernelTestBase {

  use ApigeeMockApiClientHelperTrait {
    apigeeTestHelperSetup as baseSetUp;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'apigee_edge_teams',
    'apigee_edge_teams_invitation_test',
    'apigee_edge',
    'apigee_mock_api_client',
    'key',
    'options',
    'user',
    'system',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['apigee_edge']);
    $this->installConfig(['apigee_edge_teams']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('team_member_role');
    $this->installEntitySchema('team_invitation');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);

    $this->baseSetUp();

    $this->addOrganizationMatchedResponse();
  }

  /**
   * Tests team_invitation events.
   */
  public function testEvents() {
    $team = $this->createTeam();
    $this->queueCompanyResponse($team->decorated());

    /** @var \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface $team_invitation */
    $team_invitation = TeamInvitation::create([
      'team' => ['target_id' => $team->id()],
      'team_roles' => [TeamRoleInterface::TEAM_MEMBER_ROLE],
      'recipient' => 'doe@example.com',
    ]);

    // Created.
    $team_invitation->save();
    $this->assertSame("CREATED", $team_invitation->getLabel());
    $this->assertTrue($team_invitation->isPending());

    // Declined.
    $team_invitation->setStatus(TeamInvitationInterface::STATUS_DECLINED)->save();
    $this->assertSame("DECLINED", $team_invitation->getLabel());
    $this->assertTrue($team_invitation->isDeclined());

    // Accepted.
    $team_invitation->setStatus(TeamInvitationInterface::STATUS_ACCEPTED)->save();
    $this->assertSame("ACCEPTED", $team_invitation->getLabel());
    $this->assertTrue($team_invitation->isAccepted());
  }

}
