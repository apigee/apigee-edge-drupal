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
 * @group ApigeeEdge
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
   * @var \Drupal\apigee_edge\Entity\ApiProduct[]
   */
  protected $products = [];

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

    $this->products[] = $this->createProduct();
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
    $this->drupalGet('admin/config/apigee-edge/app-settings');
    $this->drupalPostForm('admin/config/apigee-edge/app-settings', $changes + [
      'display_as_select' => FALSE,
      'associate_apps' => TRUE,
      'user_select' => TRUE,
      'multiple_products' => TRUE,
      'require' => FALSE,
      'callback_url_visible' => TRUE,
      'callback_url_required' => FALSE,
      'description_visible' => TRUE,
      'description_required' => FALSE,
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

  protected function postEditAppForm(array $data, string $app_name, ?UserInterface $account = NULL) {
    if ($account === NULL) {
      $account = $this->account;
    }

    $this->drupalPostForm("user/{$account->id()}/apps/{$app_name}/edit", $data, 'Save');
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

    $this->assertNotNull($found, 'Developer app name found.');
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
      "api_products[{$this->products[0]->getName()}]" => $this->products[0]->getName(),
    ]);
    $this->assertSession()->pageTextContains($name);
    $this->clickLink($name);

    $this->assertSession()->pageTextContains($name);
    $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());

    $this->clickLink('Delete');
    $this->submitForm([], 'Delete');

    $this->assertSession()->pageTextContains("The {$name} developer app has been deleted.");
    $apps = array_filter($this->getApps(), function(DeveloperApp $app) use($name): bool {
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
    $this->products[] = $this->createProduct();
    $this->assertAppCreationWithProduct([$this->products[0]], TRUE, FALSE, TRUE);
    $this->assertAppCreationWithProduct([$this->products[0], $this->products[1]]);
    $this->assertAppCreationWithProduct([]);
  }

  /**
   * Creates an app with no products.
   */
  public function testAppCRUDNoProducts() {
    $this->submitAdminForm(['associate_apps' => FALSE]);

    $this->assertAppCRUD();
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

    $asserts = function() {
      $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());
    };

    $this->assertAppCRUD(NULL, $asserts, NULL, $asserts);
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

    $asserts = function() {
      $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());
      $this->assertSession()->pageTextContains($this->products[1]->getDisplayName());
      $this->assertSession()->pageTextNotContains($this->products[2]->getDisplayName());
    };

    $this->assertAppCRUD(NULL, $asserts, NULL, $asserts);
  }

  /**
   * Creates an app with a single product and then removes the product.
   */
  public function testAppCRUDSingleProductRemove() {
    $this->submitAdminForm(['display_as_select' => TRUE, 'multiple_products' => FALSE]);

    $this->assertAppCRUD(
      function(array $data): array {
        $data['api_products'] = $this->products[0]->getName();
        return $data;
      },
      function() {
        $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());
      },
      function(array $data, string $credential_id): array {
        $data["credential[{$credential_id}][api_products]"] = '';
        return $data;
      },
      function() {
        $this->assertSession()->pageTextNotContains($this->products[0]->getDisplayName());
      }
    );
  }

  /**
   * Creates an app with no products and then adds one.
   */
  public function testAppCRUDSingleProductAdd() {
    $this->submitAdminForm(['multiple_products' => FALSE]);

    $this->assertAppCRUD(
      function(array $data): array {
        $data['api_products'] = '';
        return $data;
      },
      function() {
        $this->assertSession()->pageTextNotContains($this->products[0]->getDisplayName());
      },
      function(array $data, string $credential_id): array {
        $data["credential[{$credential_id}][api_products]"] = $this->products[0]->getName();
        return $data;
      },
      function() {
        $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());
      }
    );
  }

  /**
   * Creates an app with multiple products and then removes them.
   */
  public function testAppCRUDMultiplePruductsRemove() {
    $this->submitAdminForm(['display_as_select' => TRUE]);
    $this->products[] = $this->createProduct();
    $this->products[] = $this->createProduct();

    $this->assertAppCRUD(
      function(array $data): array {
        $data['api_products[]'] = [
          $this->products[0]->getName(),
          $this->products[1]->getName(),
        ];
        return $data;
      },
      function() {
        $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());
        $this->assertSession()->pageTextContains($this->products[1]->getDisplayName());
        $this->assertSession()->pageTextNotContains($this->products[2]->getDisplayName());
      },
      function(array $data, string $credential_id): array {
        $data["credential[{$credential_id}][api_products][]"] = [];
        return $data;
      },
      function() {
        $this->assertSession()->pageTextNotContains($this->products[0]->getDisplayName());
        $this->assertSession()->pageTextNotContains($this->products[1]->getDisplayName());
        $this->assertSession()->pageTextNotContains($this->products[2]->getDisplayName());
      }
    );
  }

  /**
   * Creates an app with no products and then adds multiple ones.
   */
  public function testAppCRUDMultipleProductsAdd() {
    $this->submitAdminForm([]);
    $this->products[] = $this->createProduct();
    $this->products[] = $this->createProduct();

    $this->assertAppCRUD(
      function(array $data): array {
        return $data;
      },
      function() {
        $this->assertSession()->pageTextNotContains($this->products[0]->getDisplayName());
        $this->assertSession()->pageTextNotContains($this->products[1]->getDisplayName());
        $this->assertSession()->pageTextNotContains($this->products[2]->getDisplayName());
      },
      function(array $data, string $credential_id): array {
        $data["credential[{$credential_id}][api_products][{$this->products[0]->getName()}]"] = $this->products[0]->getName();
        $data["credential[{$credential_id}][api_products][{$this->products[1]->getName()}]"] = $this->products[1]->getName();
        return $data;
      },
      function() {
        $this->assertSession()->pageTextContains($this->products[0]->getDisplayName());
        $this->assertSession()->pageTextContains($this->products[1]->getDisplayName());
        $this->assertSession()->pageTextNotContains($this->products[2]->getDisplayName());
      }
    );
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
  protected function assertAppCRUD(?callable $beforeCreate = NULL, ?callable $afterCreate = NULL, ?callable $beforeUpdate = NULL, ?callable $afterUpdate = NULL, ?UserInterface $account = NULL) {
    if ($account === NULL) {
      $account = $this->account;
    }

    $name = strtolower($this->randomMachineName());
    $displayName = $this->randomString();
    $callbackUrl = "http://example.com/{$this->randomMachineName()}";
    $description = trim($this->getRandomGenerator()->paragraphs(1));

    $data = [
      'name' => $name,
      'displayName' => $displayName,
      'callbackUrl' => $callbackUrl,
      'description' => $description,
    ];
    if ($beforeCreate) {
      $data = $beforeCreate($data);
    }

    $this->postCreateAppForm($data, $account);
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

    $displayName = $this->randomString();
    $callbackUrl = "{$callbackUrl}/{$this->randomMachineName()}";
    $description = trim($this->getRandomGenerator()->paragraphs(1));
    $data = [
      'details[displayName]' => $displayName,
      'details[callbackUrl]' => $callbackUrl,
      'details[description]' => $description,
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
    $this->submitForm([], 'Delete');

    $this->drupalGet("/user/{$account->id()}/apps");
    $this->assertSession()->pageTextNotContains($displayName);
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
