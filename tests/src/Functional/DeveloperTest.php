<?php

namespace Drupal\Tests\apigee_edge\Functional;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\Tests\BrowserTestBase;

/**
 * Create, delete, update Developer entity tests.
 *
 * @group apigee_edge
 */
class DeveloperTest extends BrowserTestBase {

  public static $modules = [
    'apigee_edge',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->profile = 'standard';
    parent::setUp();
  }

  protected function resetCache() {
    \Drupal::entityTypeManager()->getStorage('developer')->resetCache();
  }

  /**
   * Tests user/developer registration and edit.
   */
  public function testDeveloperRegister() {
    $this->drupalGet('/user/register');

    $test_user = [
      'email' => 'edge.functional.test@pronovix.com',
      'first_name' => 'Functional',
      'last_name' => 'Test',
      'username' => 'UserByAdmin',
    ];

    $formdata = [
      'mail' => $test_user['email'],
      'first_name[0][value]' => $test_user['first_name'],
      'last_name[0][value]' => $test_user['last_name'],
      'name' => $test_user['username'],
    ];
    $this->submitForm($formdata, 'Create new account');

    /** @var Developer $developer */
    $developer = Developer::load($test_user['email']);

    $this->assertEquals($developer->getEmail(), $test_user['email']);
    $this->assertEquals($developer->getFirstName(), $test_user['first_name']);
    $this->assertEquals($developer->getLastName(), $test_user['last_name']);
    $this->assertEquals($developer->getUserName(), $test_user['username']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);

    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/user/2/edit');

    $formdata['status'] = '1';
    $this->submitForm($formdata, 'Save');

    $this->resetCache();

    $developer = Developer::load($test_user['email']);

    $this->assertEquals($developer->getEmail(), $test_user['email']);
    $this->assertEquals($developer->getFirstName(), $test_user['first_name']);
    $this->assertEquals($developer->getLastName(), $test_user['last_name']);
    $this->assertEquals($developer->getUserName(), $test_user['username']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_ACTIVE);

    $developer->delete();
  }

  /**
   * Create user by admin.
   *
   * Tests creating, editing and deleting developer entity
   * if the Drupal user registered by the admin.
   */
  public function testDeveloperRegisteredByAdmin() {
    // Create blocked user by the admin.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/admin/people/create');

    $test_user = [
      'first_name' => $this->randomString(),
      'last_name' => $this->randomString(),
      'username' => $this->randomMachineName(),
      'password' => user_password(),
      'status' => '0',
    ];
    $test_user['email'] = "{$test_user['username']}@example.com";

    $formdata = [
      'mail' => $test_user['email'],
      'first_name[0][value]' => $test_user['first_name'],
      'last_name[0][value]' => $test_user['last_name'],
      'name' => $test_user['username'],
      'pass[pass1]' => $test_user['password'],
      'pass[pass2]' => $test_user['password'],
      'status' => $test_user['status'],
    ];
    $this->submitForm($formdata, 'Create new account');

    $this->resetCache();

    /** @var Developer $developer */
    $developer = Developer::load($test_user['email']);

    $this->assertEquals($developer->getEmail(), $test_user['email']);
    $this->assertEquals($developer->getFirstName(), $test_user['first_name']);
    $this->assertEquals($developer->getLastName(), $test_user['last_name']);
    $this->assertEquals($developer->getUserName(), $test_user['username']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);

    // Unblock and edit the user's email, first name, last name by the admin.
    $this->drupalGet('/user/2/edit');
    $test_user['email'] = "mod.{$test_user['email']}";
    $test_user['first_name'] = "(mod) {$test_user['first_name']}";
    $test_user['last_name'] = "(mod) {$test_user['last_name']}";
    $test_user['status'] = '1';

    $formdata['mail'] = $test_user['email'];
    $formdata['first_name[0][value]'] = $test_user['first_name'];
    $formdata['last_name[0][value]'] = $test_user['last_name'];
    $formdata['status'] = $test_user['status'];
    $this->submitForm($formdata, 'Save');

    $this->resetCache();

    $developer = Developer::load($test_user['email']);

    $this->assertEquals($developer->getEmail(), $test_user['email']);
    $this->assertEquals($developer->getFirstName(), $test_user['first_name']);
    $this->assertEquals($developer->getLastName(), $test_user['last_name']);
    $this->assertEquals($developer->getUserName(), $test_user['username']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_ACTIVE);

    // Block the user's account on the people form.
    $this->drupalGet('/admin/people');
    $this->getSession()->getPage()->selectFieldOption('edit-action', 'user_block_user_action');
    $this->getSession()->getPage()->checkField('edit-user-bulk-form-0');
    $this->getSession()->getPage()->pressButton('edit-submit');

    $this->resetCache();

    $developer = Developer::load($test_user['email']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);

    // Block user on the cancel form using the user_cancel_block method.
    $this->drupalGet('/user/2/edit');
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
    $this->submitForm($formdata, 'Save');

    $this->drupalGet('/user/2/cancel');
    $formdata = [
      'user_cancel_method' => 'user_cancel_block',
    ];
    $this->submitForm($formdata, 'Cancel account');

    $this->resetCache();

    $developer = Developer::load($test_user['email']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);

    // Block user on the cancel form using the user_cancel_reassign method.
    $this->drupalGet('/user/2/edit');
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
    $this->submitForm($formdata, 'Save');

    $this->drupalGet('/user/2/cancel');
    $formdata = [
      'user_cancel_method' => 'user_cancel_block_unpublish',
    ];
    $this->submitForm($formdata, 'Cancel account');

    $this->resetCache();

    $developer = Developer::load($test_user['email']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);

    // Delete user by admin.
    $this->drupalGet('/user/2/cancel');
    $formdata = [
      'user_cancel_method' => 'user_cancel_delete',
    ];
    $this->submitForm($formdata, 'Cancel account');

    $this->resetCache();

    $this->assertFalse(Developer::load($test_user['email']), 'Developer does not exists anymore.');
  }

}
