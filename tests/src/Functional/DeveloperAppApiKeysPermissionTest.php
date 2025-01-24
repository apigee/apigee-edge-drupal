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
  protected $consumer_key;

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
   * The developer app.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProduct
   */
  protected $apiProduct;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->addOrganizationMatchedResponse();

    $this->account = $this->createAccount([
      'add_api_key own developer_app',
    ]);

    $this->admin = $this->createAccount([
      'add_api_key any developer_app',
      'revoke_api_key any developer_app',
      'delete_api_key any developer_app',
      'view any developer_app',
      'update any developer_app',
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

    if ($keys = $this->developerApp->getCredentials()) {
      $credential = reset($keys);
      $this->consumer_key = $credential->getConsumerKey();
      $this->apiProduct = $this->createProduct();

      /** @var \Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface $appCredentialController */
      $appCredentialController = \Drupal::service('apigee_edge.controller.developer_app_credential_factory')
        ->developerAppCredentialController($this->developerApp->getAppOwner(), $this->developerApp->getName());
      $appCredentialController->addProducts($this->consumer_key, [$this->apiProduct->getName()]);
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->stack->reset();
    try {
      if ($this->account) {
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

      if ($this->apiProduct) {
        $this->apiProduct->delete();
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
    if (empty($this->consumer_key)) {
      $this->consumer_key = $this->randomMachineName(32);
      $credentials = [
        [
          "consumerKey" => $this->consumer_key,
          "consumerSecret" => $this->randomMachineName(),
          "status" => AppCredentialInterface::STATUS_APPROVED,
          "apiProducts" => [
            ["name" => $this->randomMachineName()],
          ],
        ],
      ];
      $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
      $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
      $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
      $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
      $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
      $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    }

    $this->drupalLogin($this->account);

    // Add API key.
    $add_url = $this->developerApp->toUrl('add-api-key-form');
    $this->drupalGet($add_url);
    $this->assertSession()->pageTextContains('Add key');

    // Revoke API key.
    $revoke_url = $this->developerApp->toUrl('revoke-api-key-form')
      ->setRouteParameter('consumer_key', $this->consumer_key);
    $this->drupalGet($revoke_url);
    $this->assertSession()->pageTextContains('Access denied');

    // Delete API key.
    $delete_url = $this->developerApp->toUrl('delete-api-key-form')
      ->setRouteParameter('consumer_key', $this->consumer_key);
    $this->drupalGet($delete_url);
    $this->assertSession()->pageTextContains('Access denied');

    $this->drupalLogin($this->admin);

    // Add API key.
    $add_url = $this->developerApp->toUrl('add-api-key-form');
    $this->drupalGet($add_url);
    $this->assertSession()->pageTextContains('Add key');

    if (!$this->integration_enabled) {
      $this->stack->queueMockResponse([
        'api-product' => [
          'product' => [
            'name' => $credentials[0]['apiProducts'][0]['name'],
          ],
        ],
      ]);
    }

    $this->submitForm([], 'Confirm');
    $this->assertSession()->pageTextContains('New API key added');

    if (!$this->integration_enabled) {
      $credentials[] = [
        "consumerKey" => $this->randomMachineName(32),
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
      ];

      $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
      $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    }

    // Revoke API key.
    $revoke_url = $this->developerApp->toUrl('revoke-api-key-form')
      ->setRouteParameter('consumer_key', $this->consumer_key);
    $this->drupalGet($revoke_url);
    $this->assertSession()->pageTextContains('Are you sure that you want to revoke the API key ' . $this->consumer_key . '?');

    // Delete API key.
    $delete_url = $this->developerApp->toUrl('delete-api-key-form')
      ->setRouteParameter('consumer_key', $this->consumer_key);
    $this->drupalGet($delete_url);
    $this->assertSession()->pageTextContains('Are you sure that you want to delete the API key ' . $this->consumer_key . '?');
  }

}
