<?php

/**
 * Copyright 2023 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\Tests\apigee_edge_teams\Functional\ApigeeX;

use Drupal\Core\Url;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;
use Drupal\views\Views;

/**
 * Team invitation test.
 *
 * @group apigee_edge
 * @group apigee_edge_teams
 */
class TeamInvitationsTest extends ApigeeEdgeTeamsFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'options',
    'datetime',
    'views',
    'apigee_edge_teams',
    'apigee_mock_api_client',
  ];

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $accountAdmin;

  /**
   * Authenticated user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $accountUser;

  /**
   * Team entity to test.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $teamA;

  /**
   * Team entity to test.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $teamB;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->storeToken();
    $this->addApigeexOrganizationMatchedResponse();
    $this->teamA = $this->createApigeexTeam();
    $this->teamB = $this->createApigeexTeam();

    $this->accountAdmin = $this->createAccount(['administer team']);
    $this->accountUser = $this->createAccount([
      'accept own team invitation',
    ]);

    if (!$this->integration_enabled) {
      $this->queueDeveloperResponse($this->accountAdmin);
      Developer::load($this->accountAdmin->getEmail());
      $this->queueDeveloperResponse($this->accountUser);
      Developer::load($this->accountUser->getEmail());
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (!$this->integration_enabled) {
      return;
    }
    else {
      $teams = [
        $this->teamA,
        $this->teamB,
      ];
      foreach ($teams as $team) {
        if ($team !== NULL) {
          try {
            $team->delete();
          }
          catch (\Exception $exception) {
            $this->logException($exception);
          }
        }
      }

      $accounts = [
        $this->accountAdmin,
        $this->accountUser,
      ];
      foreach ($accounts as $account) {
        if ($account !== NULL) {
          try {
            $account->delete();
          }
          catch (\Exception $exception) {
            $this->logException($exception);
          }
        }
      }
    }

    parent::tearDown();
  }

  /**
   * Tests that an email can be invited to one or more teams.
   */
  public function testMultipleInvitations() {
    $this->drupalLogin($this->accountAdmin);

    $teams = [
      $this->teamA,
      $this->teamB,
    ];
    $appgroups = [
      $this->teamA->decorated(),
      $this->teamB->decorated(),
    ];

    foreach ($teams as $team) {
      $this->queueAppGroupResponse($team->decorated());
      $this->drupalGet(Url::fromRoute('entity.team.add_members', [
        'team' => $team->id(),
      ]));

      $this->assertSession()->pageTextContains('Invite members');
      $this->submitForm([
        'developers' => $this->accountUser->getEmail(),
      ], 'Invite members');

      $successMessage = t('The following developer has been invited to the @team @team_label: @developer.', [
        '@developer' => $this->accountUser->getEmail(),
        '@team' => $team->label(),
        '@team_label' => mb_strtolower($team->getEntityType()->getSingularLabel()),
      ]);

      $this->assertSession()->pageTextContains($successMessage);
    }
  }

  /**
   * Tests that a user can see their list of invitations.
   */
  public function testInvitationsList() {
    // Ensure "team_invitations" views page is installed.
    $this->assertNotNull(Views::getView('team_invitations'));

    /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamInvitationStorageInterface $teamInvitationStorage */
    $teamInvitationStorage = $this->entityTypeManager->getStorage('team_invitation');
    $selected_roles = [TeamRoleInterface::TEAM_MEMBER_ROLE => TeamRoleInterface::TEAM_MEMBER_ROLE];
    $teams = [
      $this->teamA,
      $this->teamB,
    ];
    $appgroups = [
      $this->teamA->decorated(),
      $this->teamB->decorated(),
    ];

    // Invite user to both teams.
    foreach ($teams as $team) {
      $this->queueAppGroupResponse($team->decorated());
      $teamInvitationStorage->create([
        'team' => ['target_id' => $team->id()],
        'team_roles' => array_values(array_map(function (string $role) {
          return ['target_id' => $role];
        }, $selected_roles)),
        'recipient' => $this->accountUser->getEmail(),
      ])->save();
    }

    // Check that user can see both invitations.
    $this->drupalLogin($this->accountUser);
    $invitationsUrl = Url::fromRoute('view.team_invitations.user', [
      'user' => $this->accountUser->id(),
    ]);

    $this->queueAppGroupsResponse($appgroups);
    $this->queueDevsInCompanyResponse([]);
    $this->drupalGet($invitationsUrl);
    foreach ($teams as $team) {
      $this->assertSession()->pageTextContains('Invitation to join ' . $team->label());
    }
  }

}
