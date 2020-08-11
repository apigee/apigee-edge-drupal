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
use Apigee\Edge\Api\Management\Entity\AppCredential;
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
  public static $CONSUMER_KEY = "dMHy6YlXjGerx7uyZocwP0LOEQgcUSEp";

  /**
   * The app name.
   *
   * @var string
   */
  public static $APP_NAME = "New App";

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
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->account = $this->createAccount();
    $this->queueDeveloperResponse($this->account);
    $this->developer = Developer::load($this->account->getEmail());

    $this->developerApp = DeveloperApp::create([
      'name' => static::$APP_NAME,
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

    $this->drupalLogin($this->account);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    try {
      if ($this->developer !== NULL) {
        $this->developer->delete();
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
    $this->queueDeveloperAppResponse($this->developerApp, 200, [
      [
        "consumerKey" => static::$CONSUMER_KEY,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
      ],
      [
        "consumerKey" => $this->randomMachineName(),
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_REVOKED,
      ],
    ]);
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
    $credentials = [
      [
        // Use one api product.
        "apiProducts" => [$this->apiProducts[0]],
        "consumerKey" => static::$CONSUMER_KEY,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
      ]
    ];
    $this->queueDeveloperAppResponse($this->developerApp);
    $this->addOrganizationMatchedResponse();
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
    $this->drupalPostForm(NULL, [
      'expiry' => 'date',
      'expiry_date' => "07/20/2030",
    ], 'Confirm');
    $this->assertSession()->pageTextContains('New API key added to ' . static::$APP_NAME . '.');
    $this->assertSession()->elementContains('css', '.app-credential .api-product-list-row', 'API One');
  }

  /**
   * Tests add API key when app has multiple keys.
   *
   * @throws \Exception
   */
  public function testAppApiKeyAddMutiple() {
    // Start with two credentials with different issuedAt dates and different products.
    $credentials = [
      [
        "apiProducts" => [$this->apiProducts[0]],
        "consumerKey" => static::$CONSUMER_KEY,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
        "issuedAt" => 1594973277149,
      ],
      [
        "apiProducts" => [$this->apiProducts[1]],
        "consumerKey" => static::$CONSUMER_KEY,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
        "issuedAt" => 1594973277300,
      ]
    ];
    $this->queueDeveloperAppResponse($this->developerApp);
    $this->addOrganizationMatchedResponse();
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
    $this->drupalPostForm(NULL, [
      'expiry' => 'date',
      'expiry_date' => "07/20/2030",
    ], 'Confirm');
    $this->assertSession()->pageTextContains('New API key added to ' . static::$APP_NAME . '.');
    $this->assertSession()->elementContains('css', '.app-credential:last-child .api-product-list-row', 'API Two');
  }

  /**
   * Test app API key revoke action.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testAppApiKeyRevoke() {
    $credentials = [
      [
        "consumerKey" => static::$CONSUMER_KEY,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
      ]
    ];
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $path = $this->developerApp->toUrl('revoke-api-key-form')
      ->setRouteParameter('consumer_key', static::$CONSUMER_KEY);
    $this->drupalGet($path);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->stack->queueMockResponse('no_content');
    $this->queueDeveloperAppResponse($this->developerApp, 200, [
      [
        "consumerKey" => static::$CONSUMER_KEY,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_REVOKED,
      ],
    ]);
    $this->drupalPostForm(NULL, [], 'Revoke');
    $this->assertSession()->pageTextContains('API key with consumer key ' . static::$CONSUMER_KEY . ' revoked from ' . static::$APP_NAME . '.');
  }

  /**
   * Test app API key delete action.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testAppApiKeyDelete() {
    $credentials = [
      [
        "consumerKey" => static::$CONSUMER_KEY,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED
      ]
    ];
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $path = $this->developerApp->toUrl('delete-api-key-form')
      ->setRouteParameter('consumer_key', static::$CONSUMER_KEY);
    $this->drupalGet($path);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->queueDeveloperAppResponse($this->developerApp, 200);
    $this->stack->queueMockResponse('no_content');
    $this->drupalPostForm(NULL, [], 'Delete');
    $this->assertSession()->pageTextContains('API key with consumer key ' . static::$CONSUMER_KEY . ' deleted from ' . static::$APP_NAME . '.');
  }

}
