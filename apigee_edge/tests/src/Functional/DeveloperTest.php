<?php

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Api\Management\Entity\Developer;
use Drupal\Tests\BrowserTestBase;

/**
 * Create, delete, update Developer entity tests.
 *
 * @group ApigeeEdge
 */
class DeveloperTest extends BrowserTestBase {

  /**
   * The DeveloperController object.
   *
   * @var \Apigee\Edge\Api\Management\Controller\DeveloperController
   */
  protected $developerController;

  public static $modules = [
    'apigee_edge',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->developerController = \Drupal::service('apigee_edge.sdk_connector')->getDeveloperController();
  }

  /**
   * Create user using the registration form.
   *
   * Tests creating, editing and deleting developer entity
   * if the Drupal user registered .
   */
  public function testDeveloperRegister() {

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
      'email' => 'edge_functional_test@pronovix.com',
      'first_name' => 'Functional',
      'last_name' => 'Test',
      'username' => 'UserByAdmin',
      'password' => user_password(),
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

    $this->submitForm($formdata, 'Create new account');

    /** @var Developer $developer */
    $developer = $this->developerController->load($test_user['email']);

    $this->assertEquals($developer->getEmail(), $test_user['email']);
    $this->assertEquals($developer->getFirstName(), $test_user['first_name']);
    $this->assertEquals($developer->getLastName(), $test_user['last_name']);
    $this->assertEquals($developer->getUserName(), $test_user['username']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);

    // Unblock and edit the user's first name, last name by the admin.
    $this->drupalGet('/user/2/edit');
    $test_user['first_name'] = '(mod) Functional';
    $test_user['last_name'] = '(mod) Test';
    $test_user['status'] = '1';

    $formdata['first_name[0][value]'] = $test_user['first_name'];
    $formdata['last_name[0][value]'] = $test_user['last_name'];
    $formdata['status'] = $test_user['status'];

    $this->submitForm($formdata, 'Save');

    $developer = $this->developerController->load($test_user['email']);

    $this->assertEquals($developer->getEmail(), $test_user['email']);
    $this->assertEquals($developer->getFirstName(), $test_user['first_name']);
    $this->assertEquals($developer->getLastName(), $test_user['last_name']);
    $this->assertEquals($developer->getUserName(), $test_user['username']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_ACTIVE);

    // Block the user's account on the people page.
    //$this->drupalGet('/admin/people');
    /*$this->getSession()->getPage()->selectFieldOption('edit-action', 'user_block_user_action');
    $this->getSession()->getPage()->checkField('edit-user-bulk-form-1');
    $this->getSession()->getPage()->pressButton('edit-submit');

    $developer = $this->developerController->load($test_user['email']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);

    // Delete user by admin.
    $this->drupalGet('/user/2/cancel');
    $this->getSession()->getPage()->checkField('edit-user-cancel-method-user-cancel-delete');
    $this->getSession()->getPage()->pressButton('edit-submit');*/
  }

}
