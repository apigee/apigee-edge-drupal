<?php

/**
 * Copyright 2018 Google Inc.
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
use Drupal\Tests\apigee_edge\Traits\EntityUtilsTrait;

/**
 * Team entity UI tests.
 *
 * @group apigee_edge
 * @group apigee_edge_teams
 */
class TeamUITest extends ApigeeEdgeTeamsFunctionalTestBase {

  use EntityUtilsTrait;

  /**
   * The team entity storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamStorageInterface
   */
  protected $teamStorage;

  /**
   * Default user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * Team entity to test.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $team;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installExtraModules(['block']);
    $this->drupalPlaceBlock('local_tasks_block');
    $this->teamStorage = $this->container->get('entity_type.manager')->getStorage('team');

    $this->account = $this->createAccount([
      'create team',
      'update any team',
      'delete any team',
    ]);
    $this->drupalLogin($this->account);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    try {
      if ($this->account !== NULL) {
        $this->account->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }

    try {
      if ($this->team !== NULL) {
        $this->teamStorage->delete([$this->team]);
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }

    parent::tearDown();
  }

  /**
   * Tests the UI of the team entity.
   */
  public function testTeamUi() {
    // There are no teams on the listing page.
    $this->drupalGet(Url::fromRoute('entity.team.collection'));
    $this->assertSession()->pageTextContains('There are no Teams yet.');

    // Create a new team and check whether the link to the team is visible on
    // the listing page.
    $name = $this->getRandomGenerator()->word(10);
    $this->drupalPostForm(Url::fromRoute('entity.team.add_form'), [
      'name' => $name,
      'displayName[0][value]' => $name,
    ], 'Add team');
    $this->team = $this->teamStorage->load($name);

    $this->assertSession()->pageTextContains($name);
    $this->clickLink($name);

    // The team's display name is visible on the canonical page.
    $this->assertSession()->pageTextContains($name);

    // Update the display name and whether the updated name is visible on the
    // listing and canonical pages.
    $this->clickLink('Edit');
    $modified_display_name = $this->randomMachineName();
    $this->submitForm([
      'displayName[0][value]' => $modified_display_name,
    ], 'Save team');

    $this->assertSession()->pageTextContains($modified_display_name);
    $this->clickLink($modified_display_name);
    $this->assertSession()->pageTextContains($modified_display_name);

    // Try to delete the team without verification code.
    $this->clickLink('Delete');
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('The name does not match the team you are attempting to delete.');

    // Delete the team using correct verification code.
    $this->submitForm([
      'verification_code' => $name,
    ], 'Delete');

    // The team listing page is empty.
    $this->assertSession()->pageTextContains('There are no Teams yet.');
  }

  /**
   * Tests the team entity label modifications.
   */
  public function testTeamLabel() {
    $this->drupalLogin($this->rootUser);
    $this->changeEntityAliasesAndValidate('team', 'apigee_edge_teams.settings.team');
  }

}
