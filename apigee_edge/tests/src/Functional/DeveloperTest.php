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
   * SDKConnector object.
   *
   * @var \Drupal\apigee_edge\SDKConnector
   */
  protected $sdkConnector;

  public static $modules = [
    'apigee_edge',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->sdkConnector = \Drupal::service('apigee_edge.sdk_connector');
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

    $dc = $this->sdkConnector->getDeveloperController();
    /** @var Developer $developer */
    $developer = $dc->load($test_user['email']);

    $this->assertEquals($developer->getEmail(), $test_user['email']);
    $this->assertEquals($developer->getFirstName(), $test_user['first_name']);
    $this->assertEquals($developer->getLastName(), $test_user['last_name']);
    $this->assertEquals($developer->getUserName(), $test_user['username']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);
  }

}
