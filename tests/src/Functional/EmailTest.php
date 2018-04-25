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

/**
 * Developer email already exists related tests.
 *
 * @group apigee_edge
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
   * The Drupal user to be edited.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp() {
    parent::setUp();
    $name = strtolower($this->randomMachineName());
    $this->developer = Developer::create([
      'email' => $name . '@example.com',
      'userName' => $name,
      'firstName' => $this->getRandomGenerator()->word(8),
      'lastName' => $this->getRandomGenerator()->word(8),
    ]);
    $this->developer->save();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function tearDown() {
    $this->developer->delete();
    if ($this->account) {
      $this->account->delete();
    }
    parent::tearDown();
  }

  /**
   * Tests user registration with email that already exists in Edge.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testRegisterWithAlreadyExistingEmail() {
    $edit = [
      'name' => $this->developer->getUserName(),
      'mail' => $this->developer->getEmail(),
      'first_name[0][value]' => $this->developer->getFirstName(),
      'last_name[0][value]' => $this->developer->getLastName(),
    ];
    $this->drupalPostForm('user/register', $edit, 'Create new account');
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

  /**
   * Tests configurable already existing email address error message.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testRegisterWithAlreadyExistingEmailErrorMessage() {
    $this->drupalLogin($this->rootUser);
    $errormsg = trim($this->getRandomGenerator()->paragraphs(1));
    $this->drupalPostForm('/admin/config/apigee-edge/developer-settings', [
      'verification_action' => DeveloperSettingsForm::VERIFICATION_ACTION_DISPLAY_ERROR_ONLY,
      'display_only_error_message_content[value]' => $errormsg,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->drupalLogout();

    $edit = [
      'name' => $this->developer->getUserName(),
      'mail' => $this->developer->getEmail(),
      'first_name[0][value]' => $this->developer->getFirstName(),
      'last_name[0][value]' => $this->developer->getLastName(),
    ];
    $this->drupalPostForm('user/register', $edit, 'Create new account');
    $this->assertSession()->pageTextContains($errormsg);
  }

  /**
   * Tests changing user's email to an already existing email address in Edge.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testEditUserWithAlreadyExistingEmail() {
    $this->account = $this->createAccount();
    $this->drupalLogin($this->account);
    $this->drupalPostForm("user/{$this->account->id()}/edit", [
      'mail' => $this->developer->getEmail(),
      'current_pass' => $this->account->passRaw,
    ], 'Save');
    $this->assertSession()->pageTextContains('This email address already exists in our system. You can register a new account if you would like to use it on the Developer Portal.');

    $this->drupalLogin($this->rootUser);
    $this->drupalPostForm("user/{$this->account->id()}/edit", [
      'mail' => $this->developer->getEmail(),
    ], 'Save');
    $this->assertSession()->pageTextContains('This email address already belongs to a developer on Apigee Edge.');
  }

  /**
   * Tests the user edit form without API connection.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testEditUserWithoutAPIConnection() {
    $this->account = $this->createAccount();
    $this->drupalLogin($this->account);
    $this->invalidateKey();
    $this->drupalPostForm("user/{$this->account->id()}/edit", [
      'mail' => $this->randomGenerator->word(8) . '@example.com',
      'current_pass' => $this->account->passRaw,
    ], 'Save');
    $this->assertSession()->pageTextContains('User registration is temporarily unavailable. Try again later or contact the site administrator.');
  }

}
