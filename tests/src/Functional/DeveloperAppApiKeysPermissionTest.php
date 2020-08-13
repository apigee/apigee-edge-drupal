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

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Api\Management\Entity\App;
use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;

/**
 * Tests Api Keys permissions for developer_app.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class DeveloperAppApiKeysPermissionTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * The consumer key to use for tests.
   *
   * @var string
   */
  public static $CONSUMER_KEY = "dMHy6YlXjGerx7uyZocwP0LOEQgcUSEp";

  /**
   * A member account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $account;

  /**
   * An admin account.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $admin;

  /**
   * The owner of the developer app.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * The developer app.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $developerApp;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->account = $this->createAccount([
      'add_api_key own developer_app',
    ]);

    $this->admin = $this->createAccount([
      'add_api_key any developer_app',
      'revoke_api_key any developer_app',
      'delete_api_key any developer_app',
    ]);

    $this->queueDeveloperResponse($this->account);
    $this->developer = Developer::load($this->account->getEmail());

    $this->developerApp = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->developerApp->setOwner($this->account);
    $this->queueDeveloperAppResponse($this->developerApp);
    $this->developerApp->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $this->stack->reset();
    try {
      if ($this->account) {
        $this->queueDeveloperResponse($this->account);
        $developer = \Drupal::entityTypeManager()
          ->getStorage('developer')
          ->create([
            'email' => $this->account->getEmail(),
          ]);
        $developer->delete();
      }

      if ($this->developerApp) {
        $this->developerApp->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }

    parent::tearDown();
  }

  /**
   * Tests permissions for API key routes.
   */
  public function testPermissions() {
    $credentials = [
      [
        "consumerKey" => static::$CONSUMER_KEY,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
      ],
      [
        "consumerKey" => $this->randomMachineName(),
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
      ]
    ];

    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->addOrganizationMatchedResponse();
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->drupalLogin($this->account);

    // Add API key.
    $add_url = $this->developerApp->toUrl('add-api-key-form');
    $this->drupalGet($add_url);
    $this->assertSession()->pageTextContains('Add key');

    // Revoke API key.
    $revoke_url = $this->developerApp->toUrl('revoke-api-key-form')
      ->setRouteParameter('consumer_key', static::$CONSUMER_KEY);
    $this->drupalGet($revoke_url);
    $this->assertSession()->pageTextContains('Access denied');

    // Delete API key.
    $delete_url = $this->developerApp->toUrl('delete-api-key-form')
      ->setRouteParameter('consumer_key', static::$CONSUMER_KEY);
    $this->drupalGet($delete_url);
    $this->assertSession()->pageTextContains('Access denied');

    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->addOrganizationMatchedResponse();
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->drupalLogin($this->admin);

    // Add API key.
    $add_url = $this->developerApp->toUrl('add-api-key-form');
    $this->drupalGet($add_url);
    $this->assertSession()->pageTextContains('Add key');

    // Revoke API key.
    $revoke_url = $this->developerApp->toUrl('revoke-api-key-form')
      ->setRouteParameter('consumer_key', static::$CONSUMER_KEY);
    $this->drupalGet($revoke_url);
    $this->assertSession()->pageTextContains('Are you sure that you want to revoke the API key ' . static::$CONSUMER_KEY . '?');

    // Delete API key.
    $delete_url = $this->developerApp->toUrl('delete-api-key-form')
      ->setRouteParameter('consumer_key', static::$CONSUMER_KEY);
    $this->drupalGet($delete_url);
    $this->assertSession()->pageTextContains('Are you sure that you want to delete the API key ' . static::$CONSUMER_KEY . '?');
  }

}
