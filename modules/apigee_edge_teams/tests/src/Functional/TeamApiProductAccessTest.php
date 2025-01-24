<?php

/**
 * Copyright 2018 Google Inc.
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

namespace Drupal\Tests\apigee_edge_teams\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;

/**
 * Team-level API product access test.
 *
 * @group apigee_edge
 * @group apigee_edge_teams
 * @group apigee_edge_api_product
 * @group apigee_edge_api_product_access
 */
class TeamApiProductAccessTest extends ApigeeEdgeTeamsFunctionalTestBase {

  protected const PUBLIC_VISIBILITY = 'public';

  protected const PRIVATE_VISIBILITY = 'private';

  protected const INTERNAL_VISIBILITY = 'internal';

  protected const VISIBILITIES = [
    self::PUBLIC_VISIBILITY,
    self::PRIVATE_VISIBILITY,
    self::INTERNAL_VISIBILITY,
  ];

  protected const SUPPORTED_OPERATIONS = ['view', 'view label', 'assign'];

  /**
   * API Product entity storage.
   *
   * @var \Drupal\apigee_edge\Entity\Storage\ApiProductStorageInterface
   */
  protected $apiProductStorage;

  /**
   * Team entity storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamStorageInterface
   */
  protected $teamStorage;

  /**
   * Team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  protected $teamMembershipManager;

  /**
   * Team API product access handler.
   *
   * @var \Drupal\apigee_edge_teams\TeamMemberApiProductAccessHandlerInterface
   */
  protected $teamApiProductAccessHandler;

  /**
   * Associative array of API products where keys are the visibilities.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProductInterface[]
   */
  protected $apiProducts = [];

  /**
   * A developer who is not member of any team.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $developer;

  /**
   * A team.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Team
   */
  protected $team;

  /**
   * A developer who is member of the team and has an app with an internal prod.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $team_member;

  /**
   * The team role storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamRoleStorageInterface
   */
  protected $teamRoleStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->apiProductStorage = $this->container->get('entity_type.manager')->getStorage('api_product');
    $this->teamStorage = $this->container->get('entity_type.manager')->getStorage('team');
    $this->teamMembershipManager = $this->container->get('apigee_edge_teams.team_membership_manager');
    $this->teamApiProductAccessHandler = $this->container->get('apigee_edge_teams.team_member_api_product_access_handler');
    $this->teamRoleStorage = $this->container->get('entity_type.manager')->getStorage('team_role');

    $this->team = $this->teamStorage->create([
      'name' => $this->getRandomGenerator()->name(),
    ]);
    $this->team->save();

    foreach (['developer', 'team_member'] as $developer_property) {
      $this->{$developer_property} = $this->createAccount();
    }

    $this->teamMembershipManager->addMembers($this->team->id(), [$this->team_member->getEmail()]);

    foreach (static::VISIBILITIES as $visibility) {
      /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $api_product */
      $api_product = $this->apiProductStorage->create([
        'name' => $this->randomMachineName(),
        'displayName' => $this->randomMachineName() . " ({$visibility})",
        'approvalType' => ApiProductInterface::APPROVAL_TYPE_AUTO,
      ]);
      $api_product->setAttribute('access', $visibility);
      $api_product->save();
      $this->apiProducts[$visibility] = $api_product;
    }

    // Ensure default API product access settings.
    // Logged-in users can only access to the public API products.
    $this->config('apigee_edge.api_product_settings')
      ->set('access', [
        self::PUBLIC_VISIBILITY => [AccountInterface::AUTHENTICATED_ROLE],
        self::PRIVATE_VISIBILITY => [],
        self::INTERNAL_VISIBILITY => [],
      ])
      ->save();
    // Team members can only assign private API products to team apps but
    // they have view/view label access to public API products.
    $this->changeTeamApiProductAccess(FALSE, TRUE, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    try {
      if ($this->team !== NULL) {
        $this->teamStorage->delete([$this->team]);
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }

    foreach (['developer', 'team_member'] as $developer_property) {
      if ($this->{$developer_property}) {
        try {
          $this->{$developer_property}->delete();
        }
        catch (\Exception $exception) {
          $this->logException($exception);
        }
      }
    }

    foreach ($this->apiProducts as $product) {
      try {
        $this->apiProductStorage->delete([$product]);
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }

    parent::tearDown();
  }

  /**
   * Tests team API product access.
   */
  public function testTeamApiProductAccess() {
    // A developer's API Product access who is not a member of any teams
    // should not be affected by team-level API product access.
    $this->checkEntityAccess([
      self::PUBLIC_VISIBILITY => [
        'view',
        'view label',
        'assign',
      ],
    ], $this->developer);

    // Check team API product entity access.
    // Team member can have "assign" operation access to the public API product
    // thanks to the developer-level API product access settings.
    // Team member should not have "assign" operation access to the private API
    // product because it would mean that it can assign that to a developer app.
    $should_have_access = [
      self::PUBLIC_VISIBILITY => ['view', 'view label', 'assign'],
      self::PRIVATE_VISIBILITY => ['view', 'view label'],
    ];
    $this->checkEntityAccess($should_have_access, $this->team_member);

    // Create a developer app for team_member with internal API product.
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $team_member_app */
    $team_member_app = $this->container->get('entity_type.manager')->getStorage('developer_app')->create([
      'name' => $this->randomMachineName(),
      'status' => DeveloperAppInterface::STATUS_APPROVED,
      'developerId' => $this->team_member->get('apigee_edge_developer_id')->value,
    ]);
    $team_member_app->save();
    /** @var \Drupal\apigee_edge\SDKConnectorInterface $connector */
    $dacc = $this->container->get('apigee_edge.controller.developer_app_credential_factory')->developerAppCredentialController($this->team_member->get('apigee_edge_developer_id')->value, $team_member_app->getName());
    /** @var \Apigee\Edge\Api\Management\Entity\AppCredentialInterface $credential */
    $credentials = $team_member_app->getCredentials();
    $credential = reset($credentials);
    $dacc->addProducts($credential->getConsumerKey(), [$this->apiProducts[self::INTERNAL_VISIBILITY]->id()]);

    // Team member still should not have "view" and "view label" operation
    // access to the internal API product because it has a developer app with
    // that product. This test case ensures we did not granted "assign"
    // operation access to this user accidentally.
    $should_have_access += [
      self::INTERNAL_VISIBILITY => ['view', 'view label'],
    ];
    $this->checkEntityAccess($should_have_access, $this->team_member);

    // >>> Team member.
    $this->drupalLogin($this->team_member);
    // Team member should see only the private API product on the team app
    // creation form.
    $this->drupalGet(Url::fromRoute('entity.team_app.add_form_for_team', [
      'team' => $this->team->id(),
    ]));
    $this->assertSession()->pageTextContains($this->apiProducts[self::PRIVATE_VISIBILITY]->label());
    $this->assertSession()->pageTextNotContains($this->apiProducts[self::PUBLIC_VISIBILITY]->label());
    $this->assertSession()->pageTextNotContains($this->apiProducts[self::INTERNAL_VISIBILITY]->label());
    // After we have validated team member's entity access to the API products
    // we do not need to validate the developer app/edit forms because those
    // are covered by the parent module's ApiProductAccessTest which ensures
    // the API product list is filtered properly there.
    // \Drupal\Tests\apigee_edge\FunctionalJavascript\ApiProductAccessTest.
    $this->drupalLogout();
    // <<< Team member.
    // If team member gets removed from the team its API Product access
    // must be re-evaluated. (We have to use \Drupal::service() here to ensure
    // correct cache instances gets invalidated in TeamMembershipManager.
    // \Drupal\apigee_edge_teams\TeamMembershipManager::invalidateCaches()
    $this->teamMembershipManager->removeMembers($this->team->id(), [$this->team_member->getEmail()]);
    $should_have_access = [
      self::PUBLIC_VISIBILITY => ['view', 'view label', 'assign'],
      self::INTERNAL_VISIBILITY => ['view', 'view label'],
    ];
    $this->checkEntityAccess($should_have_access, $this->team_member);
  }

  /**
   * Checks entity operation access on all API products.
   *
   * @param array $should_have_access
   *   Associative array where keys are API product visibilities and values are
   *   entity operations that the given user should have access.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account whose access should be checked.
   */
  protected function checkEntityAccess(array $should_have_access, AccountInterface $account) {
    foreach (self::SUPPORTED_OPERATIONS as $operation) {
      foreach (self::VISIBILITIES as $visibility) {
        $expected_to_be_true = $should_have_access[$visibility] ?? [];
        $has_access = $this->apiProducts[$visibility]->access($operation, $account);
        if (in_array($operation, $expected_to_be_true)) {
          $this->assertTrue($has_access, "{$account->getDisplayName()} should have {$operation} operation access to {$this->apiProducts[$visibility]->label()} API product.");
        }
        else {
          $this->assertFalse($has_access, "{$account->getDisplayName()} should not have {$operation} operation access to {$this->apiProducts[$visibility]->label()} API product.");
        }
      }
    }
  }

  /**
   * Changes team API product access settings.
   *
   * @param bool|null $public
   *   Grant access to view public API products. NULL means do not change
   *   current settings.
   * @param bool|null $private
   *   Grant access to view private API products. NULL means do not change
   *   current settings.
   * @param bool|null $internal
   *   Grant access to view internal API products. NULL means do not change
   *   current settings.
   */
  protected function changeTeamApiProductAccess(?bool $public, ?bool $private, ?bool $internal): void {
    $rm = new \ReflectionMethod($this, __FUNCTION__);
    $permissions = [];
    foreach ($rm->getParameters() as $parameter) {
      $parameter_value = ${$parameter->getName()};
      if ($parameter_value !== NULL) {
        $permissions["api_product_access_{$parameter->getName()}"] = $parameter_value;
      }
    }

    if (!empty($permissions)) {
      $this->teamRoleStorage->changePermissions(TeamRoleInterface::TEAM_MEMBER_ROLE, $permissions);
    }
  }

}
