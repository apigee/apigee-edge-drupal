<?php

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Structure\CredentialProduct;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * @group apigee_edge
 */
class DeveloperAppUITest extends BrowserTestBase {

  protected const DUPLICATE_MACHINE_NAME = 'The machine-readable name is already in use. It must be unique.';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'apigee_edge',
  ];

  /**
   * Default user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $account;

  /**
   * Default product.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProduct
   */
  protected $product;

  /**
   * A role that can administer apigee edge and related settings.
   *
   * @var string
   */
  protected $role;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->profile = 'standard';
    parent::setUp();

    $this->role = $this->createRole([
      'administer apigee edge',
      'create developer_app',
      'view own developer_app',
      'update own developer_app',
      'delete own developer_app',
    ]);

    $this->product = $this->createProduct();
    $this->account = $this->createAccount();
    $this->drupalLogin($this->account);
  }

  /**
   * Creates a Drupal account.
   *
   * @return \Drupal\user\UserInterface
   */
  protected function createAccount() : UserInterface {
    $edit = [
      'first_name' => $this->randomMachineName(),
      'last_name' => $this->randomMachineName(),
      'name' => $this->randomMachineName(),
      'pass' => user_password(),
      'status' => TRUE,
      'roles' => [Role::AUTHENTICATED_ID, $this->role],
    ];
    $edit['mail'] = "{$edit['name']}@example.com";

    $account = User::create($edit);
    $account->save();
    // This is here to make drupalLogin() work.
    $account->passRaw = $edit['pass'];

    return $account;
  }

  /**
   * Creates a product.
   *
   * @return \Drupal\apigee_edge\Entity\ApiProduct
   */
  protected function createProduct() : ApiProduct {
    /** @var ApiProduct $product */
    $product = ApiProduct::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomString(),
      'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
    ]);
    $product->save();

    return $product;
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $this->account->delete();
    $this->product->delete();
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
    $this->drupalGet('admin/config/apigee-edge/app-settings');
    $this->drupalPostForm('admin/config/apigee-edge/app-settings', $changes + [
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

    $this->drupalPostForm("user/{$account->id()}/apps/create", $data, 'Add developer app');
  }

  /**
   * Loads all apps for a given user.
   *
   * @param null|string $email
   *
   * @return DeveloperApp[]|null
   *
   */
  protected function getApps(?string $email = NULL): ?array {
    if ($email === NULL) {
      $email = $this->account->getEmail();
    }

    $developer = Developer::load($email);
    if ($developer) {
      /** @var \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorage $storage */
      $storage = \Drupal::entityTypeManager()->getStorage('developer_app');
      return $storage->loadByDeveloper($developer->uuid());
    }

    return NULL;
  }

  /**
   * Asserts that a certain app exists.
   *
   * @param string $name
   *   Name of the app.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperApp|null
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

    $this->assertFalse($found === NULL, 'Developer app name found.');
    return $found;
  }

  /**
   * Tests the developer app label modification.
   */
  public function testDeveloperAppLabel() {
    $this->submitAdminForm([
      'app_label_singular' => 'API',
      'app_label_plural' => 'APIs',
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
      'displayName' => $name,
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
      'displayName' => $name,
    ]);
    $this->assertSession()->pageTextContains($name);
    $this->clickLink($name);

    $this->clickLink('Delete');
    $this->submitForm([], 'Delete');

    $this->assertSession()->pageTextContains("The {$name} developer app has been deleted.");
    $apps = array_filter($this->getApps(), function (DeveloperApp $app) use($name): bool {
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
      'displayName' => $name,
    ]);
    $this->assertDeveloperAppExists($name);

    $this->postCreateAppForm([
      'name' => $name,
      'displayName' => $name,
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
      'displayName' => $name,
    ]);

    $second_user = $this->createAccount();
    $this->drupalLogin($second_user);
    $this->postCreateAppForm([
      'name' => $name,
      'displayName' => $name,
    ], $second_user);
    $this->assertSession()->pageTextNotContains(static::DUPLICATE_MACHINE_NAME);

    $this->drupalLogin($this->account);
    $second_user->delete();
  }

  /**
   * Tests app creation with products.
   */
  public function testCreateAppWithProducts() {
    $extra_product = $this->createProduct();
    $this->assertAppCreationWithProduct([$this->product], TRUE, FALSE, TRUE);
    $this->assertAppCreationWithProduct([$this->product, $extra_product]);
    $this->assertAppCreationWithProduct([]);

    $extra_product->delete();
  }

  /**
   * Creates an app and assigns products to it.
   *
   * @param ApiProduct[] $products
   * @param bool $require
   *   Set the product required on the form.
   * @param bool $multiple
   *   Allow submitting multiple products.
   * @param bool $display_as_select
   *   Display the products as a select box.
   */
  protected function assertAppCreationWithProduct(array $products = [], bool $require = FALSE, bool $multiple = TRUE, bool $display_as_select = FALSE) {
    $this->submitAdminForm(['multiple_products' => $multiple, 'require' => $require, 'display_as_select' => $display_as_select]);
    $name = strtolower($this->randomMachineName());

    $productnum = count($products);

    $formdata = [
      'name' => $name,
      'displayName' => $name,
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
