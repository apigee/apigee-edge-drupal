<?php

/**
 * Copyright 2024 Google Inc.
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

namespace Drupal\Tests\apigee_edge\Functional\ApigeeX;

use Drupal\Core\Url;
use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;

/**
 * Developer email already exists in Apigee Edge related tests.
 *
 * @group apigee_edge
 * @group apigee_edge_developer
 */
class EmailTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * Tests camelcase email validation for Apigee X.
   */
  public function testEmailValidator() {
    // Skipping the test if instance type is Public.
    $instance_type = getenv('APIGEE_EDGE_INSTANCE_TYPE');
    if (!empty($instance_type) && $instance_type === EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID) {
      $this->markTestSkipped('This test suite is expecting a PUBLIC instance type.');
    }

    $this->addApigeexOrganizationMatchedResponse();

    // Admin user editing self email.
    $this->drupalLogin($this->rootUser);
    $edit = [
      'name' => $this->randomMachineName(),
      'mail' => 'aB' . $this->getRandomGenerator()->word(8) . '@example.com',
      'first_name[0][value]' => $this->getRandomGenerator()->word(8),
      'last_name[0][value]' => $this->getRandomGenerator()->word(8),
      'current_pass' => $this->rootUser->passRaw,
    ];

    $this->drupalGet(Url::fromRoute('user.edit')->toString());
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('This email address accepts only lowercase characters.');

    // Admin user editing other user account.
    $this->disableUserPresave();
    $account = $this->createAccount();
    $this->enableUserPresave();

    $this->drupalGet(Url::fromRoute('entity.user.edit_form', ['user' => $account->id()]));
    $this->submitForm([
      'mail' => 'iJk' . $this->randomMachineName() . '@example.com',
    ], 'Save');
    $this->assertSession()->pageTextContains('This email address accepts only lowercase characters.');

    // Admin user creating a new user.
    $adminuserCreate = [
      'name' => $this->randomMachineName(),
      'mail' => 'aB' . $this->getRandomGenerator()->word(8) . '@example.com',
      'first_name[0][value]' => $this->getRandomGenerator()->word(8),
      'last_name[0][value]' => $this->getRandomGenerator()->word(8),
      'pass[pass1]' => $pass = $this->randomString(),
      'pass[pass2]' => $pass,
    ];

    $this->drupalGet(Url::fromRoute('user.admin_create')->toString());
    $this->submitForm($adminuserCreate, 'Create new account');
    $this->assertSession()->pageTextContains('This email address accepts only lowercase characters.');
    $this->drupalLogout();

    // Anonmyous user creating new account.
    $userRegister = [
      'name' => $this->randomMachineName(),
      'mail' => 'xYZ' . $this->getRandomGenerator()->word(8) . '@example.com',
      'first_name[0][value]' => $this->getRandomGenerator()->word(8),
      'last_name[0][value]' => $this->getRandomGenerator()->word(8),
    ];

    $this->drupalGet(Url::fromRoute('user.register')->toString());
    $this->submitForm($userRegister, 'Create new account');
    $this->assertSession()->pageTextContains('This email address accepts only lowercase characters.');

    // User editing own account.
    $this->disableUserPresave();
    $account = $this->createAccount();
    $this->enableUserPresave();

    $this->drupalLogin($account);

    $this->drupalGet(Url::fromRoute('entity.user.edit_form', ['user' => $account->id()]));
    $this->submitForm([
      'mail' => 'pqR' . $this->randomMachineName() . '@example.com',
      'current_pass' => $account->passRaw,
    ], 'Save');
    $this->assertSession()->pageTextContains('This email address accepts only lowercase characters.');
  }

}
