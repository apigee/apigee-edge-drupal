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
use Drupal\apigee_edge\Form\DeveloperSettingsForm;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;

/**
 * Developer email already exists in Apigee Edge related tests.
 *
 * @group apigee_edge
 * @group apigee_edge_developer
 */
class EmailTest extends ApigeeEdgeFunctionalTestBase {

  use AssertMailTrait;

  /**
   * The developer entity.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    try {
      if ($this->developer !== NULL) {
        $this->developer->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    parent::tearDown();
  }

  /**
   * Tests developer email already exists in Apigee Edge.
   */
  public function testEmailValidator() {
    $name = strtolower($this->randomMachineName());
    $this->developer = Developer::create([
      'email' => $name . '@example.com',
      'userName' => $name,
      'firstName' => $this->getRandomGenerator()->word(8),
      'lastName' => $this->getRandomGenerator()->word(8),
    ]);
    $this->developer->save();

    $this->editUserWithAlreadyExistingEmailTest();
    $this->registerWithAlreadyExistingEmail();
  }

  /**
   * Tests changing user's email to an already existing email address in Edge.
   */
  public function editUserWithAlreadyExistingEmailTest() {
    // Create a new user in Drupal. It is not necessary to create a developer
    // for this user, so skip apigee_edge_user_presave().
    $this->disableUserPresave();
    $account = $this->createAccount();
    $this->enableUserPresave();

    $this->drupalLogin($account);
    $this->drupalPostForm(Url::fromRoute('entity.user.edit_form', ['user' => $account->id()]), [
      'mail' => $this->developer->getEmail(),
      'current_pass' => $account->passRaw,
    ], 'Save');
    $this->assertSession()->pageTextContains('This email address already exists in our system. You can register a new account if you would like to use it on the Developer Portal.');

    $this->drupalLogin($this->rootUser);
    $this->drupalPostForm(Url::fromRoute('entity.user.edit_form', ['user' => $account->id()]), [
      'mail' => $this->developer->getEmail(),
    ], 'Save');
    $this->assertSession()->pageTextContains('This email address already belongs to a developer on Apigee Edge.');
  }

  /**
   * Tests user registration with email that already exists in Apigee Edge.
   */
  protected function registerWithAlreadyExistingEmail() {
    $user_register_path = Url::fromRoute('user.register')->toString();
    $developer_settings_path = Url::fromRoute('apigee_edge.settings.developer')->toString();
    $edit = [
      'name' => $this->developer->getUserName(),
      'mail' => $this->developer->getEmail(),
      'first_name[0][value]' => $this->developer->getFirstName(),
      'last_name[0][value]' => $this->developer->getLastName(),
    ];

    // Display only an error message to the user.
    $this->drupalLogin($this->rootUser);
    $error_message = trim($this->getRandomGenerator()->paragraphs(1));
    $this->drupalPostForm($developer_settings_path, [
      'verification_action' => DeveloperSettingsForm::VERIFICATION_ACTION_DISPLAY_ERROR_ONLY,
      'display_only_error_message_content[value]' => $error_message,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->drupalLogout();

    $this->drupalPostForm($user_register_path, $edit, 'Create new account');
    $this->assertSession()->pageTextContains($error_message);

    // Display an error message and send a verification email to the user.
    $this->drupalLogin($this->rootUser);
    $this->drupalPostForm($developer_settings_path, [
      'verification_action' => DeveloperSettingsForm::VERIFICATION_ACTION_VERIFY_EMAIL,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->drupalLogout();

    $this->drupalPostForm($user_register_path, $edit, 'Create new account');
    $this->assertSession()->pageTextContains("This email address already exists in our system. We have sent you an verification email to {$this->developer->getEmail()}.");

    $this->assertMail('id', 'apigee_edge_developer_email_verification');
    $this->assertMail('to', $this->developer->getEmail());
    $mails = $this->getMails();
    $mail = end($mails);
    $matches = [];
    preg_match('%https?://[^/]+/user/register\?[^/\s]+%', $mail['body'], $matches);
    $link = $matches[0];

    $this->drupalPostForm($link, $edit, 'Create new account');
    $this->assertSession()->pageTextContains('A welcome message with further instructions has been sent to your email address.');
  }

}
