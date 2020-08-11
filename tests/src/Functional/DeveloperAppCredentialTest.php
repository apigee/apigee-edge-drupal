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
 * Developer app credential test.
 *
 * @group apigee_edge
 * @group apigee_edge_developer_app
 */
class DeveloperAppCredentialTest extends ApigeeEdgeFunctionalTestBase {

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
   * Tests app credential operations.
   *
   * @throws \Behat\Mink\Exception\ElementHtmlException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testAppCredentialOperations() {
    $this->queueDeveloperAppResponse($this->developerApp, 200, [
      [
        "apiProducts" => $this->apiProducts,
        "consumerKey" => static::$CONSUMER_KEY,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
      ],
      [
        "apiProducts" => $this->apiProducts,
        "consumerKey" => $this->randomMachineName(),
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_REVOKED,
      ],
    ]);
    $this->drupalGet($this->developerApp->toUrl('canonical-by-developer'));
    $this->assertSession()->elementContains('css', '.app-credential:first-child .dropbutton .revoke.dropbutton-action', 'Revoke');
    $this->assertSession()->elementContains('css', '.app-credential:first-child .dropbutton .delete.dropbutton-action', 'Delete');
  }

  /**
   * Tests credential generation.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testAppCredentialGenerate() {
    $this->queueDeveloperAppResponse($this->developerApp);
    $this->addOrganizationMatchedResponse();
    $this->stack->queueMockResponse([
      'get_api_products' => [
        'products' => $this->apiProducts,
        'expand' => TRUE,
      ],
    ]);
    $path = $this->developerApp->toUrl('generate-credential-form');
    $this->drupalGet($path);
    $this->assertSession()->pageTextContains('Generate credentials');
    $this->stack->queueMockResponse([
      'get_api_products' => [
        'products' => $this->apiProducts,
        'expand' => TRUE,
      ],
    ]);
    $this->queueDeveloperAppResponse($this->developerApp);
    $this->queueDeveloperAppResponse($this->developerApp);
    $this->drupalPostForm(NULL, [
      'expiry' => 'date',
      'expiry_date' => "07/20/2030",
      "api_products[{$this->apiProducts[0]->getName()}]" => $this->apiProducts[0]->getName(),
    ], 'Generate credential');
    $this->assertSession()->pageTextContains('New credential generated for ' . static::$APP_NAME . '.');
  }

  /**
   * Test app credential revoke action.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testAppCredentialRevoke() {
    $credentials = [
      [
        "apiProducts" => $this->apiProducts,
        "consumerKey" => static::$CONSUMER_KEY,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED,
      ]
    ];
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $path = $this->developerApp->toUrl('revoke-credential-form')
      ->setRouteParameter('consumer_key', static::$CONSUMER_KEY);
    $this->drupalGet($path);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->stack->queueMockResponse('no_content');
    $this->queueDeveloperAppResponse($this->developerApp, 200, [
      [
        "apiProducts" => $this->apiProducts,
        "consumerKey" => static::$CONSUMER_KEY,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_REVOKED,
      ],
    ]);
    $this->drupalPostForm(NULL, [], 'Revoke');
    $this->assertSession()->pageTextContains('Credential with consumer key ' . static::$CONSUMER_KEY . ' revoked from ' . static::$APP_NAME . '.');
    $this->assertSession()->elementContains('css', '.app-credential .label-status', 'Revoked');
  }

  /**
   * Test app credential delete action.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testAppCredentialDelete() {
    $credentials = [
      [
        "apiProducts" => $this->apiProducts,
        "consumerKey" => static::$CONSUMER_KEY,
        "consumerSecret" => $this->randomMachineName(),
        "status" => AppCredentialInterface::STATUS_APPROVED
      ]
    ];
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $path = $this->developerApp->toUrl('delete-credential-form')
      ->setRouteParameter('consumer_key', static::$CONSUMER_KEY);
    $this->drupalGet($path);
    $this->queueDeveloperAppResponse($this->developerApp, 200, $credentials);
    $this->queueDeveloperAppResponse($this->developerApp, 200);
    $this->stack->queueMockResponse('no_content');
    $this->drupalPostForm(NULL, [], 'Delete');
    $this->assertSession()->pageTextContains('Credential with consumer key ' . static::$CONSUMER_KEY . ' deleted from ' . static::$APP_NAME . '.');
  }

}
