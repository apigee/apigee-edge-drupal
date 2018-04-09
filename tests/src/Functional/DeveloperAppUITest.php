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

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Api\Management\Controller\DeveloperController;
use Apigee\Edge\Structure\CredentialProduct;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\user\UserInterface;

/**
 * @group apigee_edge
 * @group apigee_edge_developer_app
 */
class DeveloperAppUITest extends ApigeeEdgeFunctionalTestBase {

  protected const DUPLICATE_MACHINE_NAME = 'The machine-readable name is already in use. It must be unique.';

  /**
   * Default user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $account;

  /**
   * Default product.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProduct[]
   */
  protected $products = [];

  /**
   * A role that can administer apigee edge and related settings.
   *
   * @var string
   */
  protected $role;

  protected static $permissions = [
    'administer apigee edge',
    'create developer_app',
    'view own developer_app',
    'update own developer_app',
    'delete own developer_app',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->profile = 'standard';
    parent::setUp();

    $this->products[] = $this->createProduct();
    $this->account = $this->createAccount(static::$permissions);
    $this->drupalLogin($this->account);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $this->account->delete();
    foreach ($this->products as $product) {
      $product->delete();
    }
    parent::tearDown();
  }

  /**
   * Goes to the users' create app form.
   *
   * @param \Drupal\user\UserInterface|null $account
   */
  protected function gotoForm(?UserInterface $account = NULL) {
    if ($account === NULL) {
      $account = $this->account;
    }
    $this->drupalGet("/user/{$account->id()}/apps/create");
  }

  /**
   * Submits the create app admin form.
   *
   * @param array $changes
   *   Settings to save.
   */
  protected function submitAdminForm(array $changes = []) {
    $this->drupalGet('/admin/config/apigee-edge/app-settings');
    $this->drupalPostForm('/admin/config/apigee-edge/app-settings', $changes + [
      'display_as_select' => FALSE,
      'associate_apps' => TRUE,
      'user_select' => TRUE,
      'multiple_products' => TRUE,
      'require' => FALSE,
    ], 'Save configuration');
  }

  /**
   * Posts the create app form.
   *
   * @param array $data
   *   Data to post.
   * @param \Drupal\user\UserInterface|null $account
   *   Owner of the form.
   */
  protected function postCreateAppForm(array $data, ?UserInterface $account = NULL) {
    if ($account === NULL) {
      $account = $this->account;
    }

    $this->drupalPostForm("/user/{$account->id()}/apps/create", $data, 'Add developer app');
  }

  protected function postEditAppForm(array $data, string $app_name, ?UserInterface $account = NULL) {
    if ($account === NULL) {
      $account = $this->account;
    }

    $this->drupalPostForm("/user/{$account->id()}/apps/{$app_name}/edit", $data, 'Save');
  }

  /**
   * {@inheritdoc}
   */
  protected function getApps(?string $email = NULL): ?array {
    if ($email === NULL) {
      $email = $this->account->getEmail();
    }

    return parent::getApps($email);
  }

  /**
   * Asserts that a certain app exists.
   *
   * @param string $name
   *   Name of the app.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperApp|null
   *   Developer app or null.
   */
  protected function assertDeveloperAppExists(string $name) : ?DeveloperApp {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp[] $apps */
    $apps = $this->getApps();
    $found = NULL;
    foreach ($apps as $app) {
      if ($app->getName() === $name) {
        $found = $app;
        break;
      }
    }

    $this->assertNotNull($found, 'Developer app name found.');
    return $found;
  }

  /**
   * Tests the developer app label modification.
   */
  public function testDeveloperAppLabel() {
    $this->submitAdminForm([
      'developer_app_label_singular' => 'API',
      'developer_app_label_plural' => 'APIs',
    ]);

    \Drupal::entityTypeManager()->clearCachedDefinitions();
    menu_cache_clear_all();
    $type = \Drupal::entityTypeManager()->getDefinition('developer_app');
    $this->assertEquals('API', $type->get('label_singular'));
    $this->assertEquals('APIs', $type->get('label_plural'));
  }

  /**
   * Tests the associate apps checkbox on the admin form.
   */
  public function testAssociateApps() {
    $this->submitAdminForm(['associate_apps' => FALSE, 'user_select' => FALSE]);
    $this->gotoForm();
    $this->assertSession()->pageTextNotContains('API Product');

    $this->submitAdminForm();
    $this->gotoForm();
    $this->assertSession()->pageTextContains('API Product');
  }

  /**
   * Creates an app and tests if it is in the list.
   */
  public function testCreateAndListApp() {
    $name = strtolower($this->randomMachineName());

    $this->postCreateAppForm([
      'name' => $name,
      'displayName[0][value]' => $name,
    ]);
    $this->assertSession()->pageTextContains($name);
  }

  /**
   * Creates and deletes an app.
   */
  public function testCreateAndDeleteApp() {
    $name = strtolower($this->randomMachineName());

    $this->postCreateAppForm([
      'name' => $name,
      'displayName[0][value]' => $name,
      "api_products[{$this->products[0]->getName()}]" => $this->products[0]->getName(),
    ]);
    $this->assertSession()->pageTextContains($name);
    $this->clickLink($name);

    $this->assertSession()->pageTextContains($name);
    $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());

    $this->clickLink('Delete');
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('App name does not match app you are attempting to delete');

    $this->submitForm([
      'id_verification' => $name,
    ], 'Delete');

    $this->assertSession()->pageTextContains("The {$name} developer app has been deleted.");
    $apps = array_filter($this->getApps(), function (DeveloperApp $app) use ($name): bool {
      return $app->getName() === $name;
    });
    $this->assertEquals([], $apps, 'App is deleted');

    $this->drupalGet("user/{$this->account->id()}/apps");
    $this->assertSession()->pageTextNotContains($name);
  }

  /**
   * Tests that apps with the same name and developer can't be created.
   */
  public function testCreateDuplicateApps() {
    $name = strtolower($this->randomMachineName());

    $this->postCreateAppForm([
      'name' => $name,
      'displayName[0][value]' => $name,
    ]);
    $this->assertDeveloperAppExists($name);

    $this->postCreateAppForm([
      'name' => $name,
      'displayName[0][value]' => $name,
    ]);
    $this->assertSession()->pageTextContains(static::DUPLICATE_MACHINE_NAME);
  }

  /**
   * Tests creating two apps with the same name but different developers.
   */
  public function testSameAppNameDifferentUser() {
    $name = strtolower($this->randomMachineName());
    $this->postCreateAppForm([
      'name' => $name,
      'displayName[0][value]' => $name,
    ]);

    $second_user = $this->createAccount(static::$permissions);
    $this->drupalLogin($second_user);
    $this->postCreateAppForm([
      'name' => $name,
      'displayName[0][value]' => $name,
    ], $second_user);
    $this->assertSession()->pageTextNotContains(static::DUPLICATE_MACHINE_NAME);

    $this->drupalLogin($this->account);
    $second_user->delete();
  }

  /**
   * Tests app creation with products.
   */
  public function testCreateAppWithProducts() {
    $this->products[] = $this->createProduct();
    $this->assertAppCreationWithProduct([$this->products[0]], TRUE, FALSE, TRUE);
    $this->assertAppCreationWithProduct([$this->products[0], $this->products[1]]);
    $this->assertAppCreationWithProduct([]);
  }

  /**
   * Creates an app with no products.
   */
  public function testAppCrudNoProducts() {
    $this->submitAdminForm(['associate_apps' => FALSE]);

    $this->assertAppCrud();
  }

  /**
   * Creates an app with the default product.
   */
  public function testAppDefaultProduct() {
    $this->submitAdminForm([
      'multiple_products' => FALSE,
      'user_select' => FALSE,
      "default_api_product_multiple[{$this->products[0]->getName()}]" => $this->products[0]->getName(),
    ]);

    $asserts = function () {
      $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());
    };

    $this->assertAppCrud(NULL, $asserts, NULL, $asserts);
  }

  /**
   * Creates an app with the default products.
   */
  public function testAppDefaultProducts() {
    $this->products[] = $this->createProduct();
    $this->products[] = $this->createProduct();

    $this->submitAdminForm([
      'multiple_products' => TRUE,
      'user_select' => FALSE,
      "default_api_product_multiple[{$this->products[0]->getName()}]" => $this->products[0]->getName(),
      "default_api_product_multiple[{$this->products[1]->getName()}]" => $this->products[1]->getName(),
    ]);

    $asserts = function () {
      $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());
      $this->assertSession()->pageTextContains($this->products[1]->getDisplayName());
      $this->assertSession()->pageTextNotContains($this->products[2]->getDisplayName());
    };

    $this->assertAppCrud(NULL, $asserts, NULL, $asserts);
  }

  /**
   * Creates an app with a single product and then removes the product.
   */
  public function testAppCrudSingleProductRemove() {
    $this->submitAdminForm(['display_as_select' => TRUE, 'multiple_products' => FALSE]);

    $this->assertAppCrud(
      function (array $data): array {
        $data['api_products'] = $this->products[0]->getName();
        return $data;
      },
      function () {
        $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());
      },
      function (array $data, string $credential_id): array {
        $data["credential[{$credential_id}][api_products]"] = '';
        return $data;
      },
      function () {
        $this->assertSession()->pageTextNotContains($this->products[0]->getDisplayName());
      }
    );
  }

  /**
   * Creates an app with no products and then adds one.
   */
  public function testAppCrudSingleProductAdd() {
    $this->submitAdminForm(['multiple_products' => FALSE]);

    $this->assertAppCrud(
      function (array $data): array {
        $data['api_products'] = '';
        return $data;
      },
      function () {
        $this->assertSession()->pageTextNotContains($this->products[0]->getDisplayName());
      },
      function (array $data, string $credential_id): array {
        $data["credential[{$credential_id}][api_products]"] = $this->products[0]->getName();
        return $data;
      },
      function () {
        $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());
      }
    );
  }

  /**
   * Creates an app with multiple products and then removes them.
   */
  public function testAppCrudMultiplePruductsRemove() {
    $this->submitAdminForm(['display_as_select' => TRUE]);
    $this->products[] = $this->createProduct();
    $this->products[] = $this->createProduct();

    $this->assertAppCrud(
      function (array $data): array {
        $data['api_products[]'] = [
          $this->products[0]->getName(),
          $this->products[1]->getName(),
        ];
        return $data;
      },
      function () {
        $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());
        $this->assertSession()->pageTextContains($this->products[1]->getDisplayName());
        $this->assertSession()->pageTextNotContains($this->products[2]->getDisplayName());
      },
      function (array $data, string $credential_id): array {
        $data["credential[{$credential_id}][api_products][]"] = [];
        return $data;
      },
      function () {
        $this->assertSession()->pageTextNotContains($this->products[0]->getDisplayName());
        $this->assertSession()->pageTextNotContains($this->products[1]->getDisplayName());
        $this->assertSession()->pageTextNotContains($this->products[2]->getDisplayName());
      }
    );
  }

  /**
   * Creates an app with no products and then adds multiple ones.
   */
  public function testAppCrudMultipleProductsAdd() {
    $this->submitAdminForm([]);
    $this->products[] = $this->createProduct();
    $this->products[] = $this->createProduct();

    $this->assertAppCrud(
      function (array $data): array {
        return $data;
      },
      function () {
        $this->assertSession()->pageTextNotContains($this->products[0]->getDisplayName());
        $this->assertSession()->pageTextNotContains($this->products[1]->getDisplayName());
        $this->assertSession()->pageTextNotContains($this->products[2]->getDisplayName());
      },
      function (array $data, string $credential_id): array {
        $data["credential[{$credential_id}][api_products][{$this->products[0]->getName()}]"] = $this->products[0]->getName();
        $data["credential[{$credential_id}][api_products][{$this->products[1]->getName()}]"] = $this->products[1]->getName();
        return $data;
      },
      function () {
        $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());
        $this->assertSession()->pageTextContains($this->products[1]->getDisplayName());
        $this->assertSession()->pageTextNotContains($this->products[2]->getDisplayName());
      }
    );
  }

  /**
   * Tests the case when an account just got disabled on the edge UI.
   */
  public function testNotificationsWhenAccountIsInactiveOnEdge() {
    /** @var \Drupal\apigee_edge\SDKConnectorInterface $connector */
    $connector = \Drupal::service('apigee_edge.sdk_connector');
    $controller = new DeveloperController($connector->getOrganization(), $connector->getClient());

    $controller->setStatus($this->account->getEmail(), Developer::STATUS_INACTIVE);

    $this->drupalGet("/user/{$this->account->id()}/apps");
    $this->assertSession()->pageTextContains('Your developer account has inactive status so you will not be able to use your credentials until your account is enabled. Please contact the Developer Portal support for further assistance.');

    $this->drupalLogin($this->rootUser);
    $this->drupalGet("/user/{$this->account->id()}/apps");
    $this->assertSession()->pageTextContains("The developer account of {$this->account->getAccountName()} has inactive status so this user has invalid credentials until the account is enabled.");
  }

  /**
   * Loads a developer app by name.
   *
   * @param string $name
   *   Name of the developer app.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperApp|null
   *   Loaded developer app or null if not found.
   */
  protected function loadDeveloperApp(string $name): ?DeveloperApp {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp[] $apps */
    $apps = DeveloperApp::loadMultiple();

    foreach ($apps as $app) {
      if ($app->getName() === $name) {
        return $app;
      }
    }

    return NULL;
  }

  /**
   * Goes through a typical CRUD cycle for an app.
   *
   * @param callable|null $beforeCreate
   *   Alters the data that will be posted on the create form.
   * @param callable|null $afterCreate
   *   Additional asserts after the app is created.
   * @param callable|null $beforeUpdate
   *   Alters the data that will be posted on the update form.
   * @param callable|null $afterUpdate
   *   Additional asserts after the app is created.
   * @param \Drupal\user\UserInterface|null $account
   */
  protected function assertAppCrud(?callable $beforeCreate = NULL, ?callable $afterCreate = NULL, ?callable $beforeUpdate = NULL, ?callable $afterUpdate = NULL, ?UserInterface $account = NULL) {
    if ($account === NULL) {
      $account = $this->account;
    }

    $name = strtolower($this->randomMachineName());
    $displayName = $this->getRandomGenerator()->word(16);
    $callbackUrl = "http://example.com/{$this->randomMachineName()}";
    $description = trim($this->getRandomGenerator()->paragraphs(1));

    $data = [
      'name' => $name,
      'displayName[0][value]' => $displayName,
      'callbackUrl[0][value]' => $callbackUrl,
      'description[0][value]' => $description,
    ];
    if ($beforeCreate) {
      $data = $beforeCreate($data);
    }

    $this->postCreateAppForm($data, $account);

    $app = $this->loadDeveloperApp($name);

    $this->assertSession()->linkByHrefExists("/user/{$account->id()}/apps/{$app->getName()}/edit?destination=/user/{$account->id()}/apps");
    $this->assertSession()->linkByHrefExists("/user/{$account->id()}/apps/{$app->getName()}/delete?destination=/user/{$account->id()}/apps");
    $this->clickLink($displayName);
    $this->assertSession()->pageTextContains($displayName);
    $this->assertSession()->pageTextContains($callbackUrl);
    $this->assertSession()->pageTextContains($description);

    if ($afterCreate) {
      $afterCreate($name);
    }

    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = Developer::load($account->getEmail());
    /** @var \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('developer_app');
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = $storage->load(array_values($storage->getQuery()
      ->condition('developerId', $developer->uuid())
      ->condition('name', $name)
      ->execute())[0]);
    $this->assertNotNull($app);
    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential[] $credentials */
    $credentials = $app->getCredentials();
    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential $credential */
    $credential = reset($credentials);
    $credential_id = $credential->id();

    $displayName = $this->getRandomGenerator()->word(16);
    $callbackUrl = "{$callbackUrl}/{$this->randomMachineName()}";
    $description = trim($this->getRandomGenerator()->paragraphs(1));
    $data = [
      'displayName[0][value]' => $displayName,
      'callbackUrl[0][value]' => $callbackUrl,
      'description[0][value]' => $description,
    ];
    if ($beforeUpdate) {
      $data = $beforeUpdate($data, $credential_id);
    }

    $this->postEditAppForm($data, $name, $account);
    $this->assertSession()->pageTextContains($displayName);
    $this->assertSession()->pageTextContains($callbackUrl);
    $this->assertSession()->pageTextContains($description);

    if ($afterUpdate) {
      $afterUpdate($name);
    }

    $this->clickLink('Delete');
    $this->submitForm([
      'id_verification' => $name,
    ], 'Delete');

    $this->drupalGet("/user/{$account->id()}/apps");
    $this->assertSession()->pageTextNotContains($displayName);
  }

  /**
   * Creates an app and assigns products to it.
   *
   * @param \Drupal\apigee_edge\Entity\ApiProduct[] $products
   * @param bool $require
   *   Set the product required on the form.
   * @param bool $multiple
   *   Allow submitting multiple products.
   * @param bool $display_as_select
   *   Display the products as a select box.
   */
  protected function assertAppCreationWithProduct(array $products = [], bool $require = FALSE, bool $multiple = TRUE, bool $display_as_select = FALSE) {
    $this->submitAdminForm([
      'multiple_products' => $multiple,
      'require' => $require,
      'display_as_select' => $display_as_select,
    ]);
    $name = strtolower($this->randomMachineName());

    $productnum = count($products);

    $formdata = [
      'name' => $name,
      'displayName[0][value]' => $name,
    ];
    if (count($products) === 1) {
      $formdata['api_products'] = reset($products)->getName();
    }
    elseif (count($products) > 1) {
      foreach ($products as $product) {
        $formdata["api_products[{$product->getName()}]"] = $product->getName();
      }
    }

    $this->postCreateAppForm($formdata);
    $app = $this->assertDeveloperAppExists($name);
    if ($app) {
      /** @var \Apigee\Edge\Api\Management\Entity\AppCredential[] $credentials */
      $credentials = $app->getCredentials();
      $this->assertEquals(1, count($credentials), 'Exactly one credential exists.');
      $credential = reset($credentials);

      $apiproducts = $credential->getApiProducts();
      $this->assertEquals($productnum, count($apiproducts), "Exacly {$productnum} product is added.");
      $expected_products = array_map(function (ApiProduct $apiProduct): string {
        return $apiProduct->getName();
      }, $products);
      $retrieved_products = array_map(function (CredentialProduct $credentialProduct): string {
        return $credentialProduct->getApiproduct();
      }, $apiproducts);
      sort($expected_products);
      sort($retrieved_products);
      $this->assertEquals($expected_products, $retrieved_products);

      $app->delete();
    }
  }

}
