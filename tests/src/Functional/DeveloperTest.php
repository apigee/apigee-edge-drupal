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

use Apigee\Edge\Api\Management\Controller\CompanyController;
use Apigee\Edge\Api\Management\Controller\CompanyMembersController;
use Apigee\Edge\Api\Management\Entity\Company;
use Apigee\Edge\Api\Management\Structure\CompanyMembership;
use Drupal\apigee_edge\Entity\DeveloperInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Create, delete, update developer entity tests.
 *
 * @group apigee_edge
 * @group apigee_edge_developer
 */
class DeveloperTest extends ApigeeEdgeFunctionalTestBase {

  const USER_REGISTRATION_UNAVAILABLE = 'User registration is temporarily unavailable. Try again later or contact the site administrator.';

  /**
   * {@inheritdoc}
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
  ];

  /**
   * The developer entity storage.
   *
   * @var \Drupal\apigee_edge\Entity\Storage\DeveloperStorageInterface
   */
  protected $developerStorage;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $sdkConnector;

  /**
   * Developer entity to test.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * Company to test.
   *
   * @var \Apigee\Edge\Api\Management\Entity\CompanyInterface
   */
  protected $company;

  /**
   * The registered developer entity.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developerRegistered;

  /**
   * The developer created by admin.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developerCreatedByAdmin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Allow visitor account creation with administrative approval.
    $user_settings = $this->config('user.settings');
    $user_settings->set('register', UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL)->save(TRUE);
    $this->developerStorage = $this->container->get('entity_type.manager')->getStorage('developer');
    $this->sdkConnector = $this->container->get('apigee_edge.sdk_connector');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->stack->reset();
    try {
      if ($this->developer !== NULL) {
        $this->queueDeveloperResponseFromDeveloper($this->developer);
        $this->developer->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    try {
      if ($this->company !== NULL) {
        $this->stack->queueMockResponse(['company' => ['company' => $this->company]]);
        $company_controller = new CompanyController($this->sdkConnector->getOrganization(), $this->sdkConnector->getClient());
        $company_controller->delete($this->company->id());
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    try {
      if ($this->developerRegistered !== NULL) {
        $this->queueDeveloperResponseFromDeveloper($this->developerRegistered);
        $this->developerRegistered->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    try {
      if ($this->developerCreatedByAdmin !== NULL) {
        $this->queueDeveloperResponseFromDeveloper($this->developerCreatedByAdmin);
        $this->developerCreatedByAdmin->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    parent::tearDown();
  }

  /**
   * Tests developer registration and create by admin.
   */
  public function testDeveloperRegisterAndCreate() {
    $this->developerRegisterTest();
    $this->developerCreateByAdminTest();
    $this->developerGetCompanyListTest();
  }

  /**
   * Tests user/developer registration and edit.
   */
  protected function developerRegisterTest() {
    $test_user = [
      'email' => $this->randomMachineName() . '@example.com',
      'username' => $this->randomMachineName(),
      'first_name' => $this->getRandomGenerator()->word(16),
      'last_name' => $this->getRandomGenerator()->word(16),
    ];

    $formdata = [
      'mail' => $test_user['email'],
      'first_name[0][value]' => $test_user['first_name'],
      'last_name[0][value]' => $test_user['last_name'],
      'name' => $test_user['username'],
    ];

    // Try to register with incorrect API credentials.
    $this->invalidateKey();
    $this->drupalGet(Url::fromRoute('user.register'));
    $this->submitForm($formdata, 'Create new account');
    $this->assertSession()->pageTextContains(self::USER_REGISTRATION_UNAVAILABLE);

    // Try to register with correct API credentials.
    $this->restoreKey();

    $account = $this->entityTypeManager->getStorage('user')->create([
      'mail' => $test_user['email'],
      'name' => $test_user['username'],
      'first_name' => $test_user['first_name'],
      'last_name' => $test_user['last_name'],
    ]);

    $this->entityTypeManager->getStorage('user')->resetCache();
    $this->developerStorage->resetCache();

    // Add matched organization response so it returns the org whenever called.
    $this->addOrganizationMatchedResponse();

    // Add other responses needed to create an account. In order:
    // - 'get_not_found' - when creating an account `apigee_edge` module checks
    //   that the email does not already exist in Edge.
    // - 'get_not_found' - @to-do: why is this needed again?
    // - stack a mocked developer created response.
    $this->stack->queueMockResponse('get_not_found');
    $this->stack->queueMockResponse('get_not_found');
    $this->queueDeveloperResponse($account, 201);

    $this->drupalGet(Url::fromRoute('user.register'));
    $this->submitForm($formdata, 'Create new account');

    /** @var \Drupal\user\Entity\User $account */
    $account = user_load_by_mail($test_user['email']);
    $this->assertNotEmpty($account, 'Account is created');

    // Queue a developer response to mock the loading of a developer.
    $this->queueDeveloperResponse($account);
    $this->developerRegistered = $this->developerStorage->load($test_user['email']);
    $this->assertNotEmpty($this->developerRegistered);

    $this->assertEquals($this->developerRegistered->getEmail(), $test_user['email']);
    $this->assertEquals($this->developerRegistered->getFirstName(), $test_user['first_name']);
    $this->assertEquals($this->developerRegistered->getLastName(), $test_user['last_name']);
    $this->assertEquals($this->developerRegistered->getUserName(), $test_user['username']);
    $this->assertEquals($this->developerRegistered->getStatus(), DeveloperInterface::STATUS_INACTIVE);

    // Attribute is set by mock twig template.
    $this->assertEquals($this->developerRegistered->getAttributeValue('IS_MOCK_CLIENT'), !$this->integration_enabled);

    $this->drupalLogin($this->rootUser);
    $this->drupalGet(Url::fromRoute('entity.user.edit_form', ['user' => $account->id()]));
    $this->submitForm(['status' => '1'], 'Save');

    // Ensure status change was saved.
    $this->entityTypeManager->getStorage('user')->resetCache();
    $account = user_load_by_mail($test_user['email']);
    $this->assertTrue($account->isActive());

    // Ensure that entity static cache is also invalidated in this scope too.
    $this->developerStorage->resetCache([$test_user['email']]);

    // Queue a developer response to mock the loading of a developer.
    $this->queueDeveloperResponse($account);
    $this->developerRegistered = $this->developerStorage->loadUnchanged($test_user['email']);

    $this->assertEquals($this->developerRegistered->getEmail(), $test_user['email']);
    $this->assertEquals($this->developerRegistered->getFirstName(), $test_user['first_name']);
    $this->assertEquals($this->developerRegistered->getLastName(), $test_user['last_name']);
    $this->assertEquals($this->developerRegistered->getUserName(), $test_user['username']);
    $this->assertEquals($this->developerRegistered->getStatus(), DeveloperInterface::STATUS_ACTIVE);
  }

  /**
   * Tests creating, editing and deleting developer entity by admin.
   */
  protected function developerCreateByAdminTest() {
    // Create blocked user by admin.
    $this->drupalLogin($this->rootUser);

    $test_user = [
      'email' => $this->randomMachineName() . '@example.com',
      'first_name' => $this->getRandomGenerator()->word(16),
      'last_name' => $this->getRandomGenerator()->word(16),
      'username' => $this->randomMachineName(),
      'password' => \Drupal::service('password_generator')->generate(),
      'status' => '0',
    ];

    $formdata = [
      'mail' => $test_user['email'],
      'first_name[0][value]' => $test_user['first_name'],
      'last_name[0][value]' => $test_user['last_name'],
      'name' => $test_user['username'],
      'pass[pass1]' => $test_user['password'],
      'pass[pass2]' => $test_user['password'],
      'status' => $test_user['status'],
    ];

    // Try to register with incorrect API credentials.
    $this->invalidateKey();
    $this->drupalGet(Url::fromRoute('user.admin_create'));
    $this->submitForm($formdata, 'Create new account');
    $this->assertSession()->pageTextContains(self::USER_REGISTRATION_UNAVAILABLE);

    // Try to register with correct API credentials.
    $this->restoreKey();

    $account = $this->entityTypeManager->getStorage('user')->create([
      'mail' => $test_user['email'],
      'name' => $test_user['username'],
      'first_name' => $test_user['first_name'],
      'last_name' => $test_user['last_name'],
    ]);

    $this->entityTypeManager->getStorage('user')->resetCache();
    $this->developerStorage->resetCache();

    // Stack responses needed to create a new developer account for the account.
    $this->addOrganizationMatchedResponse();
    $this->stack->queueMockResponse('get_not_found');
    $this->stack->queueMockResponse('get_not_found');
    $this->queueDeveloperResponse($account, 201);

    $this->drupalGet(Url::fromRoute('user.admin_create'));
    $this->submitForm($formdata, 'Create new account');

    /** @var \Drupal\user\Entity\User $account */
    $account = user_load_by_mail($test_user['email']);
    $this->assertNotEmpty($account);

    // Queue a developer response to mock the loading of a developer.
    $this->queueDeveloperResponse($account);
    $this->developerCreatedByAdmin = $this->developerStorage->load($test_user['email']);
    $this->assertNotEmpty($this->developerCreatedByAdmin);

    $this->assertEquals($this->developerCreatedByAdmin->getEmail(), $test_user['email']);
    $this->assertEquals($this->developerCreatedByAdmin->getFirstName(), $test_user['first_name']);
    $this->assertEquals($this->developerCreatedByAdmin->getLastName(), $test_user['last_name']);
    $this->assertEquals($this->developerCreatedByAdmin->getUserName(), $test_user['username']);
    $this->assertEquals($this->developerCreatedByAdmin->getStatus(), DeveloperInterface::STATUS_INACTIVE);

    // Unblock and edit the user's email, first name, last name by the admin.
    $test_user['email'] = "mod.{$test_user['email']}";
    $test_user['first_name'] = "(mod) {$test_user['first_name']}";
    $test_user['last_name'] = "(mod) {$test_user['last_name']}";
    $test_user['status'] = '1';

    $formdata['mail'] = $test_user['email'];
    $formdata['first_name[0][value]'] = $test_user['first_name'];
    $formdata['last_name[0][value]'] = $test_user['last_name'];
    $formdata['status'] = $test_user['status'];

    $this->drupalGet(Url::fromRoute('entity.user.edit_form', ['user' => $account->id()]));
    $this->submitForm($formdata, 'Save');

    // Flush user entity cache to ensure the updated user gets loaded.
    // (Especially in apigee_edge_developer_app_storage_load().)
    $this->entityTypeManager->getStorage('user')->resetCache([$account->id()]);
    $account = user_load_by_mail($test_user['email']);
    $this->assertNotEmpty($account);

    // Queue a developer response to mock the loading of a developer.
    $this->queueDeveloperResponse($account);

    // Ensure that entity static cache is also invalidated in this scope.
    $this->developerCreatedByAdmin = $this->developerStorage->loadUnchanged($test_user['email']);
    $this->assertNotEmpty($this->developerCreatedByAdmin);

    $this->assertEquals($this->developerCreatedByAdmin->getEmail(), $test_user['email']);
    $this->assertEquals($this->developerCreatedByAdmin->getFirstName(), $test_user['first_name']);
    $this->assertEquals($this->developerCreatedByAdmin->getLastName(), $test_user['last_name']);
    $this->assertEquals($this->developerCreatedByAdmin->getUserName(), $test_user['username']);
    $this->assertEquals($this->developerCreatedByAdmin->getStatus(), DeveloperInterface::STATUS_ACTIVE);

    // Block the user's account on the people form.
    $this->drupalGet(Url::fromRoute('entity.user.collection'));
    $this->getSession()->getPage()->selectFieldOption('edit-action', 'user_block_user_action');
    $this->getSession()->getPage()->checkField('edit-user-bulk-form-0');
    $this->getSession()->getPage()->pressButton('edit-submit');

    // Ensure that entity static cache is also invalidated in this scope
    // too.
    // - Stack response with the blocked developer.
    $modified_dev = clone $this->developerCreatedByAdmin;
    $modified_dev->setStatus(DeveloperInterface::STATUS_INACTIVE);
    $this->queueDeveloperResponseFromDeveloper($modified_dev);
    $this->developerCreatedByAdmin = $this->developerStorage->loadUnchanged($test_user['email']);
    $this->assertEquals($this->developerCreatedByAdmin->getStatus(), DeveloperInterface::STATUS_INACTIVE);

    // Block user on the cancel form using the user_cancel_block method.
    $test_user['status'] = '1';
    $formdata = [
      'mail' => $test_user['email'],
      'first_name[0][value]' => $test_user['first_name'],
      'last_name[0][value]' => $test_user['last_name'],
      'name' => $test_user['username'],
      'pass[pass1]' => $test_user['password'],
      'pass[pass2]' => $test_user['password'],
      'status' => $test_user['status'],
    ];

    $this->drupalGet(Url::fromRoute('entity.user.edit_form', ['user' => $account->id()]));
    $this->submitForm($formdata, 'Save');

    $formdata = [
      'user_cancel_method' => 'user_cancel_block',
    ];
    if (floatval(\Drupal::VERSION) >= 9.3) {
      $this->drupalGet($account->toUrl('cancel-form')->toString());
      $this->submitForm($formdata, 'Confirm');
    }
    else {
      $this->drupalGet($account->toUrl('cancel-form')->toString());
      $this->submitForm($formdata, 'Cancel account');
    }

    $this->developerCreatedByAdmin = $this->developerStorage->load($test_user['email']);
    $this->assertNotEmpty($this->developerCreatedByAdmin);
    $this->assertEquals($this->developerCreatedByAdmin->getStatus(), DeveloperInterface::STATUS_INACTIVE);

    // Block user on the cancel form using the user_cancel_reassign method.
    $test_user['status'] = '1';
    $formdata = [
      'mail' => $test_user['email'],
      'first_name[0][value]' => $test_user['first_name'],
      'last_name[0][value]' => $test_user['last_name'],
      'name' => $test_user['username'],
      'pass[pass1]' => $test_user['password'],
      'pass[pass2]' => $test_user['password'],
      'status' => $test_user['status'],
    ];
    $this->drupalGet(Url::fromRoute('entity.user.edit_form', ['user' => $account->id()]));
    $this->submitForm($formdata, 'Save');

    $formdata = [
      'user_cancel_method' => 'user_cancel_block_unpublish',
    ];
    if (floatval(\Drupal::VERSION) >= 9.3) {
      $this->drupalGet($account->toUrl('cancel-form')->toString());
      $this->submitForm($formdata, 'Confirm');
    }
    else {
      $this->drupalGet($account->toUrl('cancel-form')->toString());
      $this->submitForm($formdata, 'Cancel account');
    }

    $this->developerCreatedByAdmin = $this->developerStorage->load($test_user['email']);
    $this->assertNotEmpty($this->developerCreatedByAdmin);
    $this->assertEquals($this->developerCreatedByAdmin->getStatus(), DeveloperInterface::STATUS_INACTIVE);

    // Delete user by admin.
    $formdata = [
      'user_cancel_method' => 'user_cancel_delete',
    ];

    if (floatval(\Drupal::VERSION) >= 9.3) {
      $this->drupalGet($account->toUrl('cancel-form')->toString());
      $this->submitForm($formdata, 'Confirm');
    }
    else {
      $this->drupalGet($account->toUrl('cancel-form')->toString());
      $this->submitForm($formdata, 'Cancel account');
    }

    // Ensure that entity static cache is also invalidated in this scope
    // too.
    $this->stack->queueMockResponse('get_not_found');
    $loaded = $this->developerStorage->loadUnchanged($test_user['email']);
    $this->assertEmpty($loaded, 'Developer does not exists anymore.');
  }

  /**
   * Tests getCompanies() and hasCompany() methods on the developer entity.
   *
   * @see \Drupal\apigee_edge\Entity\Developer::getCompanies()
   * @see \Drupal\apigee_edge\Entity\Developer::hasCompany()
   */
  public function developerGetCompanyListTest() {
    // Add matched organization response so it returns the org whenever called.
    $this->addOrganizationMatchedResponse();

    // Create a new developer.
    $name = strtolower($this->randomMachineName());
    $this->developer = $this->developerStorage->create([
      'email' => $name . '@example.com',
      'userName' => $name,
      'firstName' => $this->getRandomGenerator()->word(8),
      'lastName' => $this->getRandomGenerator()->word(8),
    ]);

    // Stack a created developer response, and an empty response
    // mocking the status change to active.
    $account = $this->queueDeveloperResponseFromDeveloper($this->developer, 201);
    $this->stack->queueMockResponse('no_content');
    $this->developer->save();

    // Result of getCompanies() function should be an empty array.
    // - Queue a developer response, as method getCompanies() loads the dev if
    // the companies are null initially.
    $this->queueDeveloperResponse($account);
    $this->developer->getCompanies();
    $this->assertNotNull($this->developer->getCompanies());
    $this->assertEmpty($this->developer->getCompanies());

    // Create a new company and add developer as a member to it.
    $this->company = new Company([
      'name' => $this->getRandomGenerator()->name(),
      'displayName' => $this->getRandomGenerator()->name(),
    ]);
    $company_controller = new CompanyController($this->sdkConnector->getOrganization(), $this->sdkConnector->getClient());
    // Queue a created company response.
    $this->queueCompanyResponse($this->company, 201);
    $company_controller->create($this->company);

    // Queue a developers in a company response.
    $this->queueDevsInCompanyResponse([
      [
        'email' => $this->developer->getEmail(),
        'role' => '',
      ],
    ]);
    $company_membership_controller = new CompanyMembersController($this->company->getName(), $this->sdkConnector->getOrganization(), $this->sdkConnector->getClient());
    $company_membership = new CompanyMembership([$this->developer->getEmail() => NULL]);
    $company_membership_controller->setMembers($company_membership);

    // Ensure that the developer is reloaded from Apigee Edge so remove the
    // developer entity from the cache.
    $this->developerStorage->resetCache([$this->developer->id()]);
    // resetCache() does not clear controller's cache by default.
    // @see \Drupal\apigee_edge\Entity\Storage\EdgeEntityStorageBase::resetCache()
    $developer_cache = $this->container->get('apigee_edge.controller.cache.developer');
    $developer_cache->removeEntities([$this->developer->id()]);

    // Check the companies array if the developer is reloaded.
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
    $this->stack->queueMockResponse([
      'developers' => [
        'developers' => [$account],
        'expand' => TRUE,
      ],
    ]);
    $this->queueDeveloperResponse($account, NULL, ['companies' => [$this->company->getName()]]);
    $developer = $this->developerStorage->loadMultiple()[$this->developer->id()];
    $this->assertContains($this->company->getName(), $developer->getCompanies());
    self::assertTrue($developer->hasCompany($this->company->getName()));

    // Check the companies array if the developer is removed from the member
    // list.
    // - Add an empty response simulating when a member is deleted.
    $this->stack->queueMockResponse('no_content');
    $company_membership_controller->removeMember($this->developer->getEmail());
    $developer_cache->removeEntities([$this->developer->id()]);
    $this->queueDeveloperResponse($account);
    $developer = $this->developerStorage->loadUnchanged($this->developer->id());
    $this->queueDeveloperResponse($account);
    $this->assertNotContains($this->company->getName(), $developer->getCompanies());
    self::assertFalse($developer->hasCompany($this->company->getName()));
  }

}
