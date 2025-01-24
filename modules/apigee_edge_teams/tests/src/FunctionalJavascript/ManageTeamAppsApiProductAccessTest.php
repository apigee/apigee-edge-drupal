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

namespace Drupal\Tests\apigee_edge_teams\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverWebAssert;
use Drupal\Tests\apigee_edge\FunctionalJavascript\ApigeeEdgeFunctionalJavascriptTestBase;
use Drupal\apigee_edge\Entity\ApiProductInterface;

/**
 * Extra validation for API product access on team app forms.
 *
 * @group apigee_edge
 * @group apigee_edge_teams
 */
class ManageTeamAppsApiProductAccessTest extends ApigeeEdgeFunctionalJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'apigee_edge_teams',
  ];

  /**
   * A user account with "Manage team apps" permission.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * A team entity.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $team;

  /**
   * A team app entity.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamAppInterface
   */
  protected $teamApp;

  /**
   * A public API product.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProductInterface
   */
  protected $publicProduct;

  /**
   * A private API product.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProductInterface
   */
  protected $privateProduct;

  /**
   * An internal API product.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProductInterface
   */
  protected $internalProduct;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Users with manage team apps permissions can see private API products.
    $this->config('apigee_edge_teams.team_settings')->set('non_member_team_apps_visible_api_products', ['private'])->save();

    $this->account = $this->createAccount([
      'manage team apps',
    ]);

    $apiProductStorage = $this->container->get('entity_type.manager')->getStorage('api_product');
    /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $api_product */
    $api_product = $apiProductStorage->create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomMachineName() . " (public)",
      'approvalType' => ApiProductInterface::APPROVAL_TYPE_AUTO,
    ]);
    $api_product->setAttribute('access', 'public');
    $api_product->save();
    $this->publicProduct = $api_product;

    /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $api_product */
    $api_product = $apiProductStorage->create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomMachineName() . " (private)",
      'approvalType' => ApiProductInterface::APPROVAL_TYPE_AUTO,
    ]);
    $api_product->setAttribute('access', 'private');
    $api_product->save();

    $this->privateProduct = $api_product;

    /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $api_product */
    $api_product = $apiProductStorage->create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomMachineName() . " (internal)",
      'approvalType' => ApiProductInterface::APPROVAL_TYPE_AUTO,
    ]);
    $api_product->setAttribute('access', 'internal');
    $api_product->save();

    $this->internalProduct = $api_product;

    /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamStorageInterface $teamStorage */
    $teamStorage = $this->container->get('entity_type.manager')->getStorage('team');
    // Use the machine name as both value because it makes easier the debugging.
    $teamName = strtolower($this->randomMachineName());
    $team = $teamStorage->create([
      'name' => $teamName,
      'displayName' => $teamName,
    ]);
    $team->save();
    $this->team = $team;
    /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamAppStorageInterface $teamAppStorage */
    $teamAppStorage = $this->container->get('entity_type.manager')->getStorage('team_app');
    $teamApp = $teamAppStorage->create([
      'name' => $this->randomMachineName(),
      'companyName' => $this->team->getName(),
    ]);
    $teamApp->save();
    $this->teamApp = $teamApp;
    /** @var \Drupal\apigee_edge_teams\Entity\Controller\TeamAppCredentialControllerFactoryInterface $teamAppCredentialControllerFactory */
    $teamAppCredentialControllerFactory = $this->container->get('apigee_edge_teams.controller.team_app_credential_controller_factory');
    $credentialController = $teamAppCredentialControllerFactory->teamAppCredentialController($this->team->id(), $this->teamApp->getName());
    $credentials = $this->teamApp->getCredentials();
    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential $credential */
    $credential = reset($credentials);

    // Assign both public and private API products to the app.
    $credentialController->addProducts($credential->getConsumerKey(), [$this->publicProduct->id(), $this->privateProduct->id()]);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->account !== NULL) {
      try {
        $this->account->delete();
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }

    if ($this->team !== NULL) {
      try {
        $this->team->delete();
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }

    if ($this->publicProduct !== NULL) {
      try {
        $this->publicProduct->delete();
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }
    if ($this->privateProduct !== NULL) {
      try {
        $this->privateProduct->delete();
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }
    if ($this->internalProduct !== NULL) {
      try {
        $this->internalProduct->delete();
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }

    parent::tearDown();
  }

  /**
   * Test API product access of a user with Manage team apps permission.
   */
  public function testManageTeamAppsApiProductAccess() {
    $assert_session = $this->assertSession();
    $message = 'You are not member of this team. You may see APIs here that a team member can not see.';
    $verifyApiProductAccessOnAddForm = function (WebDriverWebAssert $assert_session, string $message) {
      // Based on the default configuration a user with "Manage team apps"
      // permission should see the private API product but not the public
      // or the internal one.
      $this->assertSession()->pageTextContains($this->privateProduct->label());
      $this->assertSession()->pageTextNotContains($this->publicProduct->label());
      $this->assertSession()->pageTextNotContains($this->internalProduct->label());
    };
    $this->drupalLogin($this->account);

    // Validate team app add forms.
    $this->drupalGet($this->teamApp->toUrl('add-form'));
    $verifyApiProductAccessOnAddForm($assert_session, $message);
    $assert_session->selectExists('Owner')->selectOption($this->team->id());
    $assert_session->assertWaitOnAjaxRequest(1200000);
    $this->assertSession()->pageTextContains($message);

    $this->drupalGet(Url::fromRoute('entity.team_app.add_form_for_team', ['team' => $this->team->id()]));
    $verifyApiProductAccessOnAddForm($assert_session, $message);
    $this->assertSession()->pageTextContains($message);

    // Validate team app edit form.
    $this->drupalGet($this->teamApp->toUrl('edit-form'));
    // The page should contain both public and private API products, because
    // the team app is in association with the public API product.
    $this->assertSession()->pageTextContains($this->privateProduct->label());
    $this->assertSession()->pageTextContains($this->publicProduct->label());
    // But it still should not contain the internal API product.
    $this->assertSession()->pageTextNotContains($this->internalProduct->label());
    $this->assertSession()->pageTextContains($message);
  }

}
