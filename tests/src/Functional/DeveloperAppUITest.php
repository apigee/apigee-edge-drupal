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
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\Core\Url;
use Drupal\Tests\apigee_edge\Traits\EntityUtilsTrait;

/**
 * Developer app UI tests.
 *
 * @group apigee_edge
 * @group apigee_edge_developer_app
 */
class DeveloperAppUITest extends ApigeeEdgeFunctionalTestBase {

  use DeveloperAppUITestTrait;
  use EntityUtilsTrait;

  protected const DUPLICATE_MACHINE_NAME = 'The machine-readable name is already in use. It must be unique.';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // We can not override self::$modules in this trait because that would
    // conflict with \Drupal\Tests\BrowserTestBase::$modules where both
    // traits are being used.
    $this->installExtraModules(['block']);
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('system_breadcrumb_block');

    $config = $this->config('apigee_edge.dangerzone');
    $config->set('skip_developer_app_settings_validation', TRUE);
    $config->save();

    $this->products[] = $this->createProduct();
    $this->account = $this->createAccount(static::$permissions);
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
    foreach ($this->products as $product) {
      try {
        if ($product !== NULL) {
          $product->delete();
        }
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }
    parent::tearDown();
  }

  /**
   * Tests the developer app label modification.
   */
  public function testDeveloperAppLabel() {
    $this->changeEntityAliasesAndValidate('developer_app', 'apigee_edge.settings.developer_app');
  }

  /**
   * Tests the user_select checkbox on the admin form.
   */
  public function testAssociateApps() {
    $this->submitAdminForm([
      'user_select' => FALSE,
      "default_api_product_multiple[{$this->products[0]->getName()}]" => $this->products[0]->getName(),
    ]);
    $this->gotoCreateAppForm();
    $this->assertSession()->pageTextNotContains('APIs');

    $this->submitAdminForm();
    $this->gotoCreateAppForm();
    $this->assertSession()->pageTextContains('APIs');
  }

  /**
   * Creates an app and tests if it is in the list.
   */
  public function testCreateAndListApp() {
    $name = strtolower($this->randomMachineName());

    $this->postCreateAppForm([
      'name' => $name,
      'displayName[0][value]' => $name,
      "api_products[{$this->products[0]->getName()}]" => $this->products[0]->getName(),
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
    $this->assertSession()->pageTextContains($this->products[0]->label());

    $this->clickLink('Delete');
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('The name does not match the app you are attempting to delete.');

    $this->submitForm([
      'verification_code' => $name,
    ], 'Delete');

    $this->assertSession()->pageTextContains("The {$name} app has been deleted.");
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
      "api_products[{$this->products[0]->getName()}]" => $this->products[0]->getName(),
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
      "api_products[{$this->products[0]->getName()}]" => $this->products[0]->getName(),
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
    $this->assertAppCreationWithProduct([$this->products[0]], FALSE, TRUE);
    $this->assertAppCreationWithProduct([$this->products[0], $this->products[1]]);
  }

  /**
   * Tests app creation with modified credential lifetime.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testCreateAppWithModifiedCredentialLifetime() {
    $url = Url::fromRoute('apigee_edge.settings.developer_app.credentials');
    // Change credential lifetime to 10 days from 0.
    $this->drupalPostForm($url, [
      'credential_lifetime' => 10,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Create a new developer app and check the credential expiration.
    $name = strtolower($this->randomMachineName());
    $this->postCreateAppForm([
      'name' => $name,
      'displayName[0][value]' => $name,
      "api_products[{$this->products[0]->getName()}]" => $this->products[0]->getName(),
    ]);
    $this->assertSession()->pageTextContains($name);
    $this->clickLink($name);
    // Result depends on how fast the response was.
    $this->assertSession()->pageTextMatches('/1 week (2|3) days hence/');

    // Change credential lifetime to 0 (Never) days from 10.
    $this->drupalPostForm($url, [
      'credential_lifetime' => 0,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Create a new developer app and check the credential expiration.
    $name = strtolower($this->randomMachineName());
    $this->postCreateAppForm([
      'name' => $name,
      'displayName[0][value]' => $name,
      "api_products[{$this->products[0]->getName()}]" => $this->products[0]->getName(),
    ]);
    $this->assertSession()->pageTextContains($name);
    $this->clickLink($name);
    $this->assertSession()->pageTextContains('Never');
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
      $this->assertSession()->pageTextContains($this->products[0]->label());
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
      $this->assertSession()->pageTextContains($this->products[0]->label());
      $this->assertSession()->pageTextContains($this->products[1]->label());
      $this->assertSession()->pageTextNotContains($this->products[2]->label());
    };

    $this->assertAppCrud(NULL, $asserts, NULL, $asserts);
  }

  /**
   * Creates an app with a single product and then removes the product.
   */
  public function testAppCrudSingleProductChange() {
    $this->submitAdminForm(['display_as_select' => TRUE, 'multiple_products' => FALSE]);
    $this->products[] = $this->createProduct();

    $this->assertAppCrud(
      function (array $data): array {
        $data['api_products'] = $this->products[0]->getName();
        return $data;
      },
      function () {
        $this->assertSession()->pageTextContains($this->products[0]->label());
      },
      function (array $data, string $credential_id): array {
        $data["credential[{$credential_id}][api_products]"] = $this->products[1]->getName();
        return $data;
      },
      function () {
        $this->assertSession()->pageTextNotContains($this->products[0]->label());
        $this->assertSession()->pageTextContains($this->products[1]->label());
      }
    );
  }

  /**
   * Creates an app with no products and then adds one.
   */
  public function testAppCrudSingleProductAdd() {
    $this->submitAdminForm(['multiple_products' => FALSE]);

    $this->products[] = $this->createProduct();

    $this->assertAppCrud(
      function (array $data): array {
        $data['api_products'] = $this->products[1]->getName();
        return $data;
      },
      function () {
        $this->assertSession()->pageTextContains($this->products[1]->label());
      },
      function (array $data, string $credential_id): array {
        $data["credential[{$credential_id}][api_products]"] = $this->products[0]->getName();
        return $data;
      },
      function () {
        $this->assertSession()->pageTextContains($this->products[0]->label());
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
        $this->assertSession()->pageTextContains($this->products[0]->label());
        $this->assertSession()->pageTextContains($this->products[1]->label());
        $this->assertSession()->pageTextNotContains($this->products[2]->label());
      },
      function (array $data, string $credential_id): array {
        $data["credential[{$credential_id}][api_products][]"] = [
          $this->products[2]->getName(),
        ];
        return $data;
      },
      function () {
        $this->assertSession()->pageTextNotContains($this->products[0]->label());
        $this->assertSession()->pageTextNotContains($this->products[1]->label());
        $this->assertSession()->pageTextContains($this->products[2]->label());
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
        $data["api_products[{$this->products[2]->getName()}]"] = $this->products[2]->getName();
        return $data;
      },
      function () {
        $this->assertSession()->pageTextNotContains($this->products[0]->label());
        $this->assertSession()->pageTextNotContains($this->products[1]->label());
        $this->assertSession()->pageTextContains($this->products[2]->label());
      },
      function (array $data, string $credential_id): array {
        $data["credential[{$credential_id}][api_products][{$this->products[0]->getName()}]"] = $this->products[0]->getName();
        $data["credential[{$credential_id}][api_products][{$this->products[1]->getName()}]"] = $this->products[1]->getName();
        $data["credential[{$credential_id}][api_products][{$this->products[2]->getName()}]"] = "";
        return $data;
      },
      function () {
        $this->assertSession()->pageTextContains($this->products[0]->label());
        $this->assertSession()->pageTextContains($this->products[1]->label());
        $this->assertSession()->pageTextNotContains($this->products[2]->label());
      }
    );
  }

  /**
   * Tests the case when an account just got disabled on the edge UI.
   */
  public function testNotificationsWhenAccountIsInactiveOnEdge() {
    /** @var \Drupal\apigee_edge\SDKConnectorInterface $connector */
    $connector = $this->container->get('apigee_edge.sdk_connector');
    $controller = new DeveloperController($connector->getOrganization(), $connector->getClient());

    $controller->setStatus($this->account->getEmail(), Developer::STATUS_INACTIVE);

    $this->drupalGet("/user/{$this->account->id()}/apps");
    $this->assertSession()->pageTextContains('Your developer account has inactive status so you will not be able to use your credentials until your account gets activated. Please contact support for further assistance.');

    $this->drupalLogin($this->rootUser);
    $this->drupalGet("/user/{$this->account->id()}/apps");
    $this->assertSession()->pageTextContains("The developer account of {$this->account->getDisplayName()} has inactive status so this user has invalid credentials until the account gets activated.");
  }

  /**
   * Ensures warning messages are visible if multiple products/app is disabled.
   */
  public function testWarningMessagesIfMultipleProductsDisabled() {
    $admin_warning_message = 'Access to multiple API products will be retained until an app is edited and the developer is prompted to confirm a single API Product selection.';
    $end_user_warning_message = 'Foos now require selection of a single Bar; multiple Bar selection is no longer supported. Confirm your Bar selection below.';
    $app_settings_url = Url::fromRoute('apigee_edge.settings.general_app');

    // Ensure default configuration.
    $this->config('apigee_edge.common_app_settings')
      ->set('multiple_products', TRUE)
      ->save();

    // Change default Developer App and API Product aliases to ensure consumer
    // warning message respects it.
    $this->config('apigee_edge.developer_app_settings')
      ->set('entity_label_singular', 'Foo')
      ->set('entity_label_plural', 'Foos')
      ->save();
    $this->config('apigee_edge.api_product_settings')
      ->set('entity_label_singular', 'Bar')
      ->set('entity_label_plural', 'Bars')
      ->save();
    \Drupal::entityTypeManager()->clearCachedDefinitions();

    $this->products[] = $product1 = $this->createProduct();;
    $this->products[] = $product2 = $this->createProduct();
    $app = $this->createDeveloperApp(['name' => $this->randomMachineName(), 'displayName' => $this->randomString()], $this->account, [$product1->id(), $product2->id()]);
    $app_edit_url = $app->toUrl('edit-form-for-developer');

    $this->drupalGet($app_settings_url);
    $this->assertSession()->pageTextNotContains($admin_warning_message);

    $this->drupalGet($app_edit_url);
    $this->assertSession()->pageTextNotContains($end_user_warning_message);

    // Change default configuration.
    $this->config('apigee_edge.common_app_settings')
      ->set('multiple_products', FALSE)
      ->save();

    $this->drupalGet($app_settings_url);
    $this->assertSession()->pageTextContains($admin_warning_message);

    $this->drupalGet($app_edit_url);
    $this->assertSession()->pageTextContains($end_user_warning_message);
  }

  /**
   * Tests callback url validation on the server-side.
   */
  public function testCallbackUrlValidationServerSide() {
    // Override default configuration.
    $description = 'This is a Callback URL field.';
    $this->config('apigee_edge.common_app_settings')
      ->set('callback_url_pattern', '^https:\/\/example.com')
      ->set('callback_url_description', $description)
      ->save();

    $callback_url = $this->randomMachineName();
    $this->products[] = $product = $this->createProduct();;
    $app = $this->createDeveloperApp([
      'name' => $callback_url,
      'displayName' => $this->randomString(),
      'callbackUrl' => $callback_url,
    ], $this->account, [$product->id()]);
    $app_edit_url = $app->toUrl('edit-form-for-developer');

    $this->drupalGet($app_edit_url);
    // Also test field description.
    $this->assertSession()->pageTextContains($description);
    $this->drupalPostForm($app_edit_url, [], 'Save');
    $this->assertSession()->pageTextContains("The URL {$callback_url} is not valid.");
    $this->drupalPostForm($app_edit_url, ['callbackUrl[0][value]' => 'http://example.com'], 'Save');
    $this->assertSession()->pageTextContains("Callback URL field is not in the right format.");
    $this->drupalPostForm($app_edit_url, ['callbackUrl[0][value]' => 'https://example.com'], 'Save');
    $this->assertSession()->pageTextContains('App has been successfully updated.');
    $this->assertSession()->pageTextContains('https://example.com');
  }

  /**
   * Ensures warning message is visible if callback url's value is invalid.
   */
  public function testInvalidEdgeSideCallbackUrl() {
    $this->drupalLogin($this->rootUser);
    $this->products[] = $this->createProduct();
    $callback_url = $this->randomGenerator->word(8);
    $callback_url_warning_msg = "The Callback URL value should be fixed. The URI '{$callback_url}' is invalid. You must use a valid URI scheme.";
    $app = $this->createDeveloperApp([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomString(),
      'callbackUrl' => $callback_url,
    ],
      $this->account,
      [
        $this->products[0]->id(),
      ]);

    $app_view_url = $app->toUrl('canonical');
    $app_view_by_developer_url = $app->toUrl('canonical-by-developer');
    $app_edit_form_url = $app->toUrl('edit-form');
    $app_edit_form_for_developer_url = $app->toUrl('edit-form-for-developer');

    $this->drupalGet($app_view_url);
    $this->assertSession()->pageTextContains($callback_url_warning_msg);
    $this->assertSession()->pageTextNotContains('Callback URL:');
    $this->drupalGet($app_view_by_developer_url);
    $this->assertSession()->pageTextContains($callback_url_warning_msg);
    $this->assertSession()->pageTextNotContains('Callback URL:');

    $this->drupalGet($app_edit_form_url);
    $this->assertSession()->fieldValueEquals('callbackUrl[0][value]', $callback_url);
    $this->drupalGet($app_edit_form_for_developer_url);
    $this->assertSession()->fieldValueEquals('callbackUrl[0][value]', $callback_url);

    $this->drupalPostForm(Url::fromRoute('entity.entity_view_display.developer_app.default'), ['fields[callbackUrl][region]' => 'hidden'], 'Save');
    $this->drupalPostForm(Url::fromRoute('entity.entity_form_display.developer_app.default'), ['fields[callbackUrl][region]' => 'hidden'], 'Save');

    $this->drupalGet($app_view_url);
    $this->assertSession()->pageTextNotContains($callback_url_warning_msg);
    $this->assertSession()->pageTextNotContains('Callback URL:');

    $this->drupalGet($app_view_by_developer_url);
    $this->assertSession()->pageTextNotContains($callback_url_warning_msg);
    $this->assertSession()->pageTextNotContains('Callback URL:');
  }

  /**
   * Ensures breadcrumb is properly displayed on the developer app pages.
   */
  public function testBreadcrumbOnDeveloperAppPages() {
    $this->drupalLogin($this->rootUser);
    $user = $this->createAccount();

    // Check UID 2 Apps page.
    $this->drupalGet(Url::fromRoute('entity.developer_app.collection_by_developer', ['user' => $this->account->id()]));
    $breadcrumb_links = $this->getBreadcrumbLinks();
    $this->assertEquals('/', $breadcrumb_links[0]->getAttribute('href'));
    $this->assertEquals(Url::fromRoute('entity.user.canonical', ['user' => $this->account->id()])->toString(), $breadcrumb_links[1]->getAttribute('href'));

    // Check UID 2 create app page.
    $this->drupalGet(Url::fromRoute('entity.developer_app.add_form_for_developer', ['user' => $this->account->id()]));
    $breadcrumb_links = $this->getBreadcrumbLinks();
    $this->assertEquals('/', $breadcrumb_links[0]->getAttribute('href'));
    $this->assertEquals(Url::fromRoute('entity.user.canonical', ['user' => $this->account->id()])->toString(), $breadcrumb_links[1]->getAttribute('href'));
    $this->assertEquals(Url::fromRoute('entity.developer_app.collection_by_developer', ['user' => $this->account->id()])->toString(), $breadcrumb_links[2]->getAttribute('href'));

    // Check UID 3 apps page.
    $this->drupalGet(Url::fromRoute('entity.developer_app.collection_by_developer', ['user' => $user->id()]));
    $breadcrumb_links = $this->getBreadcrumbLinks();
    $this->assertEquals('/', $breadcrumb_links[0]->getAttribute('href'));
    $this->assertEquals(Url::fromRoute('entity.user.canonical', ['user' => $user->id()])->toString(), $breadcrumb_links[1]->getAttribute('href'));

    // Check UID 3 create app page.
    $this->drupalGet(Url::fromRoute('entity.developer_app.add_form_for_developer', ['user' => $user->id()]));
    $expected_breadcrumb[] = Url::fromRoute('entity.developer_app.collection_by_developer', [
      'user' => $user->id(),
    ])->toString();
    $breadcrumb_links = $this->getBreadcrumbLinks();
    $this->assertEquals('/', $breadcrumb_links[0]->getAttribute('href'));
    $this->assertEquals(Url::fromRoute('entity.user.canonical', ['user' => $user->id()])->toString(), $breadcrumb_links[1]->getAttribute('href'));
    $this->assertEquals(Url::fromRoute('entity.developer_app.collection_by_developer', ['user' => $user->id()])->toString(), $breadcrumb_links[2]->getAttribute('href'));
  }

}
