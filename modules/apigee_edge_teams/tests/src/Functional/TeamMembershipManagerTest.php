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

/**
 * Team membership manager service test.
 *
 * @group apigee_edge
 * @group apigee_edge_teams
 */
class TeamMembershipManagerTest extends ApigeeEdgeTeamsFunctionalTestBase {

  /**
   * The developer entity storage.
   *
   * @var \Drupal\apigee_edge\Entity\Storage\DeveloperStorageInterface
   */
  protected $developerStorage;

  /**
   * The team entity storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamStorageInterface
   */
  protected $teamStorage;

  /**
   * Team entity to test.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $team;

  /**
   * Array of developers to test.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface[]
   */
  protected $developers;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->developerStorage = $this->container->get('entity_type.manager')->getStorage('developer');
    $this->teamStorage = $this->container->get('entity_type.manager')->getStorage('team');

    for ($i = 0; $i < 2; $i++) {
      $name = strtolower($this->randomMachineName());
      /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
      $developer = $this->developerStorage->create([
        'email' => $name . '@example.com',
        'userName' => $name,
        'firstName' => $this->getRandomGenerator()->word(8),
        'lastName' => $this->getRandomGenerator()->word(8),
      ]);
      $developer->save();
      $this->developers[$i] = $developer;
    }

    $this->team = $this->teamStorage->create([
      'name' => $this->getRandomGenerator()->name(),
    ]);
    $this->team->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    foreach ($this->developers as $developer) {
      try {
        $this->developerStorage->delete([$developer]);
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }

    if ($this->team !== NULL) {
      try {
        $this->teamStorage->delete([$this->team]);
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }

    parent::tearDown();
  }

  /**
   * Tests team membership manager service.
   */
  public function testTeamMembershipManager() {
    $team_membership_manager = $this->container->get('apigee_edge_teams.team_membership_manager');
    $team_membership_cache = $this->container->get('apigee_edge_teams.cache.company_membership_object');

    // Ensure that the company's member list is empty.
    foreach ($this->developers as $developer) {
      $this->assertEmpty($developer->getCompanies());
      $this->assertEmpty($team_membership_manager->getTeams($developer->getEmail()));
      $this->assertEmpty($team_membership_manager->getMembers($this->team->getName()));
    }
    // Ensure that team membership cache is empty.
    $this->assertEmpty($team_membership_cache->getMembership($this->team->getName())->getMembers());

    // Add developers to the team and check whether the related membership
    // service functions work properly.
    $team_membership_manager->addMembers($this->team->getName(), [$this->developers[0]->getEmail(), $this->developers[1]->getEmail()]);
    foreach ($this->developers as $developer) {
      $this->assertContains($this->team->getName(), $team_membership_manager->getTeams($developer->getEmail()));
      $this->assertContains($developer->getEmail(), $team_membership_manager->getMembers($this->team->getName()));

      // Check whether the team membership is correctly cached.
      $this->assertArrayHasKey($developer->getEmail(), $team_membership_cache->getMembership($this->team->getName())->getMembers());
    }

    // Remove developers from the team and check whether the related
    // membership service functions work properly.
    foreach ($this->developers as $developer) {
      $team_membership_manager->removeMembers($this->team->getName(), [$developer->getEmail()]);
      $this->assertNotContains($this->team->getName(), $team_membership_manager->getTeams($developer->getEmail()));
      $this->assertNotContains($developer->getEmail(), $team_membership_manager->getMembers($this->team->getName()));

      // Check whether the team membership is correctly cached.
      $this->assertArrayNotHasKey($developer->getEmail(), $team_membership_cache->getMembership($this->team->getName())->getMembers());
    }

    // Ensure that team membership cache is empty.
    $this->assertEmpty($team_membership_cache->getMembership($this->team->getName())->getMembers());

    // Add developer to company then delete developer and check whether the
    // developer is no longer member of the team.
    $team_membership_manager->addMembers($this->team->getName(), [$this->developers[0]->getEmail()]);
    $this->developerStorage->delete([$this->developers[0]]);
    $this->assertNotContains($this->developers[0]->getEmail(), $team_membership_manager->getMembers($this->team->getName()));
    // Check whether the team membership is correctly cached.
    $this->assertArrayNotHasKey($this->developers[0]->getEmail(), $team_membership_cache->getMembership($this->team->getName())->getMembers());

    // Delete the team and ensure that the team is removed from the team
    // membership cache.
    $this->teamStorage->delete([$this->team]);
    $this->assertNull($team_membership_cache->getMembership($this->team->getName()));
  }

}
