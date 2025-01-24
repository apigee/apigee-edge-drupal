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

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Api\Management\Entity\App;
use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;

/**
 * Developer app API key test.
 *
 * @group apigee_edge
 * @group apigee_edge_developer_app
 */
class DeveloperAppApiKeyTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * The consumer key to use for tests.
   *
   * @var string
   */
  protected $consumer_key;

  /**
   * {@inheritdoc}
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * The Drupal user that belongs to the developer app's developer.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The owner of the developer app.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * Developer app to test.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $developerApp;

  /**
   * An array of API products to test.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProductInterface[]
   */
  protected $apiProducts;

  /**
   * The AppCredentialController service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface
   */
  protected $appCredentialController;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->appCredentialController = \Drupal::service('apigee_edge.controller.developer_app_credential_factory');

    $this->account = $this->createAccount([
      'add_api_key own developer_app',
      'revoke_api_key own developer_app',
      'delete_api_key own developer_app',
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

    $productOne = ApiProduct::create([
      'name' => 'api_one',
      'displayName' => 'API One',
      'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
    ]);
    $this->stack->queueMockResponse([
      'api_product' => [
        'product' => $productOne,
      ],
    ]);
    $productOne->save();

    $productTwo = ApiProduct::create([
      'name' => 'api_two',
      'displayName' => 'API Two',
      'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
    ]);
    $this->stack->queueMockResponse([
      'api_product' => [
        'product' => $productTwo,
      ],
    ]);
    $productTwo->save();

    $this->apiProducts = [$productOne, $productTwo];

    if ($keys = $this->developerApp->getCredentials()) {
      $credential = reset($keys);
      $this->consumer_key = $credential->getConsumerKey();
    }

    $this->addOrganizationMatchedResponse();

    $this->drupalLogin($this->account);
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
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    try {
      if ($this->apiProducts !== NULL) {
        foreach ($this->apiProducts as $product) {
          $product->delete();
        }
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    parent::tearDown();
  }

  /**
   * Tests app API key operations.
   *
   * @throws \Behat\Mink\Exception\ElementHtmlException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testAppApiKeyOperations() {
    if (empty($this->consumer_key)) {
      $this->consumer_key = $this->randomMachineName(32);

      $credentials = [
        [
          "consumerKey" => $this->consumer_key,
          "consumerSecret" => $this->randomMachineName(),
          "status" => AppCredentialInterface::STATUS_APPROVED,
        ],
        [
          "consumerKey" => $this->randomMachineName(),
          "consumerSecret" => $this->randomMachineName(),
          "status" => AppCredentialInterface::STATUS_APPROVED,
        ],
        [
          "consumerKey" => $this->randomMachineName(),
          "consumerSecret" => $this->randomMachineName(),
          "status" => AppCredentialInterface::STATUS_REVOKED,
        ],
      ];
      $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    }
    else {
      $credController = $this->appCredentialController->developerAppCredentialController($this->developerApp->getAppOwner(), $this->developerApp->getName());
      $credController->create($this->randomMachineName(), $this->randomMachineName());
      $credController->create($this->randomMachineName(), $this->randomMachineName());
    }

    $this->drupalGet($this->developerApp->toUrl('canonical-by-developer'));
    $this->assertSession()->elementContains('css', '.app-credential:first-child .dropbutton', 'Revoke');
    $this->assertSession()->elementContains('css', '.app-credential:first-child .dropbutton', 'Delete');
  }

  /**
   * Tests add API key when app has only one existing key.
   *
   * @throws \Exception
   */
  public function testAppApiKeyAddSingle() {
    $credentials = [];
    if (empty($this->consumer_key)) {
      $this->consumer_key = $this->randomMachineName(32);

      $credentials = [
        [
          // Use one api product.
          "apiProducts" => [$this->apiProducts[0]],
          "consumerKey" => $this->consumer_key,
          "consumerSecret" => $this->randomMachineName(),
          "status" => AppCredentialInterface::STATUS_APPROVED,
        ],
      ];
    }
    else {
      $credController = $this->appCredentialController->developerAppCredentialController($this->developerApp->getAppOwner(), $this->developerApp->getName());
      $credController->addProducts($this->consumer_key, [$this->apiProducts[0]->getName()]);
    }

    $path = $this->developerApp->toUrl('add-api-key-form');

    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->drupalGet($path);
    $this->assertSession()->pageTextContains('Add key');
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->stack->queueMockResponse([
      'api_product' => [
        'product' => $this->apiProducts[0],
      ],
    ]);
    $this->submitForm([
      'expiry' => 'date',
      'expiry_date' => "07/20/2030",
    ], 'Confirm');
    $this->assertSession()->pageTextContains('New API key added to ' . $this->developerApp->getName() . '.');
    $this->assertSession()->elementContains('css', '.app-credential .api-product-list-row', 'API One');
  }

  /**
   * Tests add API key when app has multiple keys.
   *
   * @throws \Exception
   */
  public function testAppApiKeyAddMultiple() {
    // Start with two credentials with different issuedAt dates and different products.
    $credentials = [];
    if (empty($this->consumer_key)) {
      $this->consumer_key = $this->randomMachineName(32);

      $credentials = [
        [
          "apiProducts" => [$this->apiProducts[0]],
          "consumerKey" => $this->consumer_key,
          "consumerSecret" => $this->randomMachineName(),
          "status" => AppCredentialInterface::STATUS_APPROVED,
          "issuedAt" => 1594973277149,
        ],
        [
          "apiProducts" => [$this->apiProducts[1]],
          "consumerKey" => $this->randomMachineName(32),
          "consumerSecret" => $this->randomMachineName(),
          "status" => AppCredentialInterface::STATUS_APPROVED,
          "issuedAt" => 1594973277300,
        ],
      ];
    }
    else {
      $credController = $this->appCredentialController->developerAppCredentialController($this->developerApp->getAppOwner(), $this->developerApp->getName());
      $credController->addProducts($this->consumer_key, [$this->apiProducts[0]->getName()]);
      $credController->generate([$this->apiProducts[1]->getName()], $this->developerApp->getAttributes(), '');
    }

    $this->queueDeveloperAppResponse($this->developerApp);
    $path = $this->developerApp->toUrl('add-api-key-form');
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->drupalGet($path);
    $this->assertSession()->pageTextContains('Add key');
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->stack->queueMockResponse([
      'api_product' => [
        'product' => $this->apiProducts[0],
      ],
    ]);
    $this->stack->queueMockResponse([
      'api_product' => [
        'product' => $this->apiProducts[1],
      ],
    ]);
    $this->submitForm([
      'expiry' => 'date',
      'expiry_date' => "07/20/2030",
    ], 'Confirm');
    $this->assertSession()->pageTextContains('New API key added to ' . $this->developerApp->getName() . '.');
    $this->assertSession()->elementContains('css', '.app-credential:last-child .api-product-list-row', 'API Two');
  }

  /**
   * Test app API key revoke action.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testAppApiKeyRevoke() {
    $credentials = [];
    if (empty($this->consumer_key)) {
      $this->consumer_key = $this->randomMachineName(32);

      $credentials = [
        [
          "consumerKey" => $this->consumer_key,
          "consumerSecret" => $this->randomMachineName(),
          "status" => AppCredentialInterface::STATUS_APPROVED,
        ],
        [
          "consumerKey" => $this->randomMachineName(),
          "consumerSecret" => $this->randomMachineName(),
          "status" => AppCredentialInterface::STATUS_APPROVED,
        ],
      ];
    }
    else {
      $credController = $this->appCredentialController->developerAppCredentialController($this->developerApp->getAppOwner(), $this->developerApp->getName());
      $credController->create($this->randomMachineName(), $this->randomMachineName());
    }

    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $path = $this->developerApp->toUrl('revoke-api-key-form')
      ->setRouteParameter('consumer_key', $this->consumer_key);
    $this->drupalGet($path);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->stack->queueMockResponse('no_content');

    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->submitForm([], 'Revoke');
    $this->assertSession()->pageTextContains('API key ' . $this->consumer_key . ' revoked from ' . $this->developerApp->getName() . '.');

    // Access denied for the only active key.
    $credentials = [
      [
        "consumerKey" => $this->consumer_key,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
      ],
    ];
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->drupalGet($path);
    $this->assertSession()->pageTextContains('Access denied');
  }

  /**
   * Test app API key delete action.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testAppApiKeyDelete() {
    $credentials = [];
    if (empty($this->consumer_key)) {
      $this->consumer_key = $this->randomMachineName(32);

      $credentials = [
        [
          "consumerKey" => $this->consumer_key,
          "consumerSecret" => $this->randomMachineName(),
          "status" => AppCredentialInterface::STATUS_APPROVED,
        ],
        [
          "consumerKey" => $this->randomMachineName(),
          "consumerSecret" => $this->randomMachineName(),
          "status" => AppCredentialInterface::STATUS_APPROVED,
        ],
      ];
    }
    else {
      $credController = $this->appCredentialController->developerAppCredentialController($this->developerApp->getAppOwner(), $this->developerApp->getName());
      $credController->create($this->randomMachineName(), $this->randomMachineName());
    }

    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $path = $this->developerApp->toUrl('delete-api-key-form')
      ->setRouteParameter('consumer_key', $this->consumer_key);
    $this->drupalGet($path);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    unset($credentials[0]);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->stack->queueMockResponse('no_content');
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('API key ' . $this->consumer_key . ' deleted from ' . $this->developerApp->getName() . '.');

    // Access denied for the only active key.
    $credentials = [
      [
        "consumerKey" => $this->consumer_key,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
      ],
    ];
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->drupalGet($path);
    $this->assertSession()->pageTextContains('Access denied');
  }

}
