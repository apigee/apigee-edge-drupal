<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\Tests\apigee_edge_teams\Functional;

use Drupal\Core\Url;
use Drupal\apigee_edge\Entity\Developer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apigee Edge Teams list builder tests.
 *
 * @group apigee_edge
 * @group apigee_edge_teams
 */
class TeamListBuilderTest extends ApigeeEdgeTeamsFunctionalTestBase {

  /**
   * Indicates this test class is mock API client ready.
   *
   * @var bool
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'options',
    'key',
    'apigee_edge',
    'apigee_edge_teams',
    'apigee_mock_api_client',
  ];

  /**
   * The team entity storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamStorageInterface
   */
  protected $teamStorage;

  /**
   * The user 1 account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * Drupal user who is a member team A.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $aMemberAccount;

  /**
   * Drupal user who is a member team B.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $bMemberAccount;

  /**
   * Drupal user who is an admin.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $cMemberAccount;

  /**
   * Team A entity to test.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $teamA;

  /**
   * Team B entity to test.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $teamB;

  /**
   * A role.
   *
   * @var \Drupal\user\Entity\Role
   */
  protected $customRole;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // This state acts as a "kill switch." Our overridden
    // `getFromPersistentCache()` method checks for this state.
    // When it's TRUE, that method will *always* return [], forcing
    // a cache miss and guaranteeing that every test gets fresh data
    // from the source (the API) instead of the cache.
    $this->state = $this->container->get('state');
    $this->state->set('apigee_teams_test_skip_cache', TRUE);

    $this->addOrganizationMatchedResponse();

    $this->teamStorage = $this->entityTypeManager->getStorage('team');

    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('apigee_edge_teams.team_settings');
    $config->set('cache_expiration', 300);
    $config->save(TRUE);

    // Create accounts: user 1, for members of two teams, and an extra one.
    $this->account = $this->rootUser;
    $this->aMemberAccount = $this->createNewAccount();
    $this->bMemberAccount = $this->createNewAccount();
    $this->cMemberAccount = $this->createNewAccount();

    $this->customRole = $this->drupalCreateRole(['view any team']);

    // Create teams.
    $this->teamA = $this->createTeam();
    $this->teamB = $this->createTeam();

    // Add accounts to teams.
    $this->addUserToTeam($this->teamA, $this->aMemberAccount);
    $this->addUserToTeam($this->teamB, $this->bMemberAccount);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();

    try {
      $this->teamStorage->delete([$this->teamA, $this->teamB]);
      $this->account->delete();
      $this->aMemberAccount->delete();
      $this->bMemberAccount->delete();
      $this->cMemberAccount->delete();
    }
    catch (\Error $error) {
      // Do nothing.
    }
    catch (\Exception $exception) {
      // Do nothing.
    }
  }

  /**
   * Tests team list cache.
   */
  public function testTeamListCache() {
    $companies = [
      $this->teamA->decorated(),
      $this->teamB->decorated(),
    ];

    // aMemberAccount should only see teamA.
    $this->drupalLogin($this->aMemberAccount);
    $this->queueDeveloperResponse($this->aMemberAccount, 200, ['companies' => [$this->teamA->id()]]);
    $this->queueCompanyResponse($this->teamA->decorated());
    $this->queueCompanyResponse($this->teamA->decorated());
    $this->drupalGet(Url::fromRoute('entity.team.collection'));
    $assert = $this->assertSession();
    $assert->pageTextContains($this->teamA->label());
    $assert->pageTextNotContains($this->teamB->label());
    $this->drupalLogout();

    // bMemberAccount should only see teamB.
    $this->drupalLogin($this->bMemberAccount);
    $this->queueDeveloperResponse($this->bMemberAccount, 200, ['companies' => [$this->teamB->id()]]);
    $this->queueCompanyResponse($this->teamB->decorated());
    $this->queueCompanyResponse($this->teamB->decorated());
    $this->drupalGet(Url::fromUserInput('/teams'));
    $assert = $this->assertSession();
    $assert->pageTextNotContains($this->teamA->label());
    $assert->pageTextContains($this->teamB->label());
    $this->drupalLogout();

    // cMemberAccount should see both teams now.
    $this->drupalLogin($this->cMemberAccount);
    // Give cMemberAccount permission to view all teams.
    $this->cMemberAccount->addRole($this->customRole);
    $this->cMemberAccount->save();
    $this->queueCompaniesResponse($companies);
    $this->drupalGet(Url::fromUserInput('/teams'));
    $assert = $this->assertSession();
    $assert->pageTextContains($this->teamA->label());
    $assert->pageTextContains($this->teamB->label());

    // cMemberAccount should not see any teams.
    $this->cMemberAccount->removeRole($this->customRole);
    $this->cMemberAccount->save();
    $this->queueCompaniesResponse($companies);
    $this->queueDeveloperResponse($this->cMemberAccount);
    $this->queueDeveloperResponse($this->cMemberAccount);
    $this->drupalGet(Url::fromUserInput('/teams'));
    $assert = $this->assertSession();
    $assert->pageTextNotContains($this->teamA->label());
    $assert->pageTextNotContains($this->teamB->label());
  }

  /**
   * Helper function to create a random user account.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The user account.
   */
  protected function createNewAccount() {
    $this->disableUserPresave();
    $account = $this->createAccount();

    $fields = [
      'email' => $account->getEmail(),
      'userName' => $account->getAccountName(),
      'firstName' => $this->getRandomGenerator()->word(8),
      'lastName' => $this->getRandomGenerator()->word(8),
    ];

    // Stack developer responses for "created" and "set active".
    $this->queueDeveloperResponse($account, Response::HTTP_CREATED);
    $this->stack->queueMockResponse('no_content');
    $developer = Developer::create($fields);
    $developer->save();

    return $account;
  }

}
