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

namespace Drupal\Tests\apigee_edge_teams\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apigee Edge Teams list builder tests.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 * @group apigee_edge_teams
 * @group apigee_edge_teams_kernel
 */
class TeamListBuilderTest extends KernelTestBase {

  use ApigeeMockApiClientHelperTrait, UserCreationTrait;

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
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('team_role');
    $this->installEntitySchema('team_member_role');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['user', 'apigee_edge', 'apigee_edge_teams']);

    $this->apigeeTestHelperSetup();

    $this->teamStorage = $this->entityTypeManager->getStorage('team');

    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('apigee_edge_teams.team_settings');
    $config->set('cache_expiration', 300);
    $config->save(TRUE);

    // Create teams.
    $this->teamA = $this->createTeam();
    $this->teamB = $this->createTeam();

    // Create accounts: user 1, and for members of two teams.
    $this->account = $this->createAccount();
    $this->aMemberAccount = $this->createAccount();
    $this->bMemberAccount = $this->createAccount();

    // Add accounts to teams.
    $this->addUserToTeam($this->teamA, $this->aMemberAccount);
    $this->addUserToTeam($this->teamB, $this->bMemberAccount);

    $this->addOrganizationMatchedResponse();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    try {
      $this->teamStorage->delete([$this->teamA, $this->teamB]);
      $this->account->delete();
      $this->aMemberAccount->delete();
      $this->bMemberAccount->delete();
    }
    catch (\Error $error) {
      // Do nothing.
    }
  }

  /**
   * Tests team list cache.
   */
  public function testTeamListCache() {
    $accountSwitcher = \Drupal::service('account_switcher');

    $accountSwitcher->switchTo($this->aMemberAccount);

    $this->setRawContent($this->getRenderedList());
    $this->assertText($this->teamA->label());
    $this->assertNoText($this->teamB->label());

    $accountSwitcher->switchTo($this->bMemberAccount);

    $this->setRawContent($this->getRenderedList());
    $this->assertNoText($this->teamA->label());
    $this->assertText($this->teamB->label());
  }

  /**
   * Helper function to get the HTML output of the Team List.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The rendered HTML.
   */
  protected function getRenderedList() {
    $renderer = \Drupal::service('renderer');
    $listBuilder = $this->entityTypeManager->getListBuilder('team');

    $this->stack->queueMockResponse(['companies' => [
      'companies' => [$this->teamA->decorated(), $this->teamB->decorated()],
    ]]);
    $element = $listBuilder->render();
    $element['#cache']['keys'] = ['test'];

    return $renderer->renderRoot($element);
  }

  /**
   * Helper function to create a random user account.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The user account.
   */
  protected function createAccount() {
    $account = $this->entityTypeManager->getStorage('user')->create([
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => $this->randomMachineName(),
      'first_name' => $this->getRandomGenerator()->word(16),
      'last_name' => $this->getRandomGenerator()->word(16),
    ]);
    $this->queueDeveloperResponse($account, Response::HTTP_CREATED);
    $account->save();

    return $account;
  }

}
