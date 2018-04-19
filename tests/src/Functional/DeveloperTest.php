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

use Drupal\apigee_edge\Entity\Developer;

/**
 * Create, delete, update Developer entity tests.
 *
 * @group apigee_edge
 * @group apigee_edge_developer
 */
class DeveloperTest extends ApigeeEdgeFunctionalTestBase {

  public static $modules = [
    'apigee_edge_test',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Allow visitor account creation with administrative approval.
    $user_settings = \Drupal::configFactory()->getEditable('user.settings');
    $user_settings->set('register', USER_REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL)->save(TRUE);
  }

  /**
   * Tests user/developer registration and edit.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function testDeveloperRegister() {
    $this->drupalGet('/user/register');

    $test_user = [
      'username' => $this->randomMachineName(),
      'first_name' => $this->getRandomGenerator()->word(16),
      'last_name' => $this->getRandomGenerator()->word(16),
    ];
    $test_user['email'] = "{$test_user['username']}@example.com";

    $formdata = [
      'mail' => $test_user['email'],
      'first_name[0][value]' => $test_user['first_name'],
      'last_name[0][value]' => $test_user['last_name'],
      'name' => $test_user['username'],
    ];
    $this->submitForm($formdata, 'Create new account');

    /** @var \Drupal\user\Entity\User $account */
    $account = user_load_by_mail($test_user['email']);
    $this->assertNotEmpty($account, 'Account is created');

    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = Developer::load($test_user['email']);
    $this->assertNotEmpty($developer);

    $this->assertEquals($developer->getEmail(), $test_user['email']);
    $this->assertEquals($developer->getFirstName(), $test_user['first_name']);
    $this->assertEquals($developer->getLastName(), $test_user['last_name']);
    $this->assertEquals($developer->getUserName(), $test_user['username']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);

    $this->drupalLogin($this->rootUser);
    $this->drupalGet("/user/{$account->id()}/edit");

    $formdata['status'] = '1';
    $this->submitForm($formdata, 'Save');

    // Ensure that entity static cache is also invalidated in this scope
    // too.
    \Drupal::entityTypeManager()->getStorage('developer')->resetCache([$test_user['email']]);
    // Load developer entity by UUID.
    $developer = Developer::load($account->get('apigee_edge_developer_id')->value);

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
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function testDeveloperRegisteredByAdmin() {
    // Create blocked user by the admin.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/admin/people/create');

    $test_user = [
      'first_name' => $this->getRandomGenerator()->word(16),
      'last_name' => $this->getRandomGenerator()->word(16),
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

    /** @var \Drupal\user\Entity\User $account */
    $account = user_load_by_mail($test_user['email']);
    $this->assertNotEmpty($account);

    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = Developer::load($test_user['email']);
    $this->assertNotEmpty($developer);

    $this->assertEquals($developer->getEmail(), $test_user['email']);
    $this->assertEquals($developer->getFirstName(), $test_user['first_name']);
    $this->assertEquals($developer->getLastName(), $test_user['last_name']);
    $this->assertEquals($developer->getUserName(), $test_user['username']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);

    // Unblock and edit the user's email, first name, last name by the admin.
    $this->drupalGet("/user/{$account->id()}/edit");
    $test_user['email'] = "mod.{$test_user['email']}";
    $test_user['first_name'] = "(mod) {$test_user['first_name']}";
    $test_user['last_name'] = "(mod) {$test_user['last_name']}";
    $test_user['status'] = '1';

    $formdata['mail'] = $test_user['email'];
    $formdata['first_name[0][value]'] = $test_user['first_name'];
    $formdata['last_name[0][value]'] = $test_user['last_name'];
    $formdata['status'] = $test_user['status'];
    $this->submitForm($formdata, 'Save');

    $account = user_load_by_mail($test_user['email']);
    $this->assertNotEmpty($account);

    // Ensure that entity static cache is also invalidated in this scope
    // too. TODO Maybe introduce a loadUnchanged() method on developer or
    // use storage's loadUnchanged() instead.
    \Drupal::entityTypeManager()->getStorage('developer')->resetCache([$test_user['email']]);
    $developer = Developer::load($test_user['email']);
    $this->assertNotEmpty($developer);

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

    // Ensure that entity static cache is also invalidated in this scope
    // too. TODO Maybe introduce a loadUnchanged() method on developer or
    // use storage's loadUnchanged() instead.
    \Drupal::entityTypeManager()->getStorage('developer')->resetCache([$test_user['email']]);
    $developer = Developer::load($test_user['email']);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);

    // Block user on the cancel form using the user_cancel_block method.
    $this->drupalGet("/user/{$account->id()}/edit");
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

    $this->drupalGet("/user/{$account->id()}/cancel");
    $formdata = [
      'user_cancel_method' => 'user_cancel_block',
    ];
    $this->submitForm($formdata, 'Cancel account');

    $developer = Developer::load($test_user['email']);
    $this->assertNotEmpty($developer);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);

    // Block user on the cancel form using the user_cancel_reassign method.
    $this->drupalGet("/user/{$account->id()}/edit");
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

    $this->drupalGet("/user/{$account->id()}/cancel");
    $formdata = [
      'user_cancel_method' => 'user_cancel_block_unpublish',
    ];
    $this->submitForm($formdata, 'Cancel account');

    $developer = Developer::load($test_user['email']);
    $this->assertNotEmpty($developer);
    $this->assertEquals($developer->getStatus(), $developer::STATUS_INACTIVE);

    // Delete user by admin.
    $this->drupalGet("/user/{$account->id()}/cancel");
    $formdata = [
      'user_cancel_method' => 'user_cancel_delete',
    ];
    $this->submitForm($formdata, 'Cancel account');

    // Ensure that entity static cache is also invalidated in this scope
    // too. TODO Maybe introduce a loadUnchanged() method on developer or
    // use storage's loadUnchanged() instead.
    \Drupal::entityTypeManager()->getStorage('developer')->resetCache([$test_user['email']]);
    $this->assertFalse(Developer::load($test_user['email']), 'Developer does not exists anymore.');
  }

}
