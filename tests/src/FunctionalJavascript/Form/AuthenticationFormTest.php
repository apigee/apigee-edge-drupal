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

namespace Drupal\Tests\apigee_edge\FunctionalJavascript\Form;

use Drupal\apigee_edge\Form\AuthenticationForm;
use Drupal\Core\Url;
use Drupal\key\Entity\Key;
use Drupal\Tests\apigee_edge\FunctionalJavascript\ApigeeEdgeFunctionalJavascriptTestBase;

/**
 * Apigee Edge API credentials, authentication form, key integration test.
 *
 * @group apigee_edge
 * @group apigee_edge_javascript
 */
class AuthenticationFormTest extends ApigeeEdgeFunctionalJavascriptTestBase {

  /**
   * Valid credentials.
   *
   * @var array
   */
  protected $validCredentials = [];

  /**
   * IDs of the created keys.
   *
   * @var array
   */
  protected $keys = [];

  /**
   * Initializes the credentials property.
   *
   * @return bool
   *   True if the credentials are successfully initialized.
   */
  protected function initCredentials(): bool {
    if (($auth_type = getenv('APIGEE_EDGE_AUTH_TYPE'))) {
      $this->validCredentials['auth_type'] = $auth_type;
    }
    if (($username = getenv('APIGEE_EDGE_USERNAME'))) {
      $this->validCredentials['username'] = $username;
    }
    if (($password = getenv('APIGEE_EDGE_PASSWORD'))) {
      $this->validCredentials['password'] = $password;
    }
    if (($organization = getenv('APIGEE_EDGE_ORGANIZATION'))) {
      $this->validCredentials['organization'] = $organization;
    }
    if (($endpoint = getenv('APIGEE_EDGE_ENDPOINT'))) {
      $this->validCredentials['endpoint'] = $endpoint;
    }

    return (bool) $this->validCredentials;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    if (!$this->initCredentials()) {
      $this->markTestSkipped('Credentials not found.');
    }
    parent::setUp();
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests Apigee Edge key types, key providers and authentication form.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ElementTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAuthenticationForm() {
    $web_assert = $this->assertSession();
    $active_key = Key::load($this->config(AuthenticationForm::CONFIG_NAME)->get('active_key'));
    /** @var \Drupal\apigee_edge\Plugin\KeyType\ApigeeAuthKeyType $active_key_type */
    $active_key_type = $active_key->getKeyType();
    $active_password = $active_key_type->getPassword($active_key);
    $active_username = $active_key_type->getUsername($active_key);
    $active_org      = $active_key_type->getOrganization($active_key);
    $active_endpoint = $active_key_type->getEndpoint($active_key);

    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));
    $page = $this->getSession()->getPage();

    // Test that the visible connection settings match the token values.
    $web_assert->fieldValueEquals('Organization', $active_org);
    $web_assert->fieldValueEquals('Username', $active_username);

    // Tests the default settings.
    $web_assert->fieldValueEquals('Authentication type', 'basic');
    $web_assert->fieldValueEquals('Password', '');
    $web_assert->fieldValueEquals('Apigee Edge endpoint', $active_endpoint);

    // Make sure the oauth fields are hidden.
    $this->assertFalse($this->cssSelect('#edit-key-input-settings-authorization-server')[0]->isVisible());
    $this->assertFalse($this->cssSelect('#edit-key-input-settings-client-id')[0]->isVisible());
    $this->assertFalse($this->cssSelect('#edit-key-input-settings-client-secret')[0]->isVisible());

    // Switch to oauth.
    $this->cssSelect('#edit-key-input-settings-auth-type')[0]->setValue('oauth');
    // Make sure the oauth fields are visible.
    $this->assertTrue($this->cssSelect('#edit-key-input-settings-authorization-server')[0]->isVisible());
    $this->assertTrue($this->cssSelect('#edit-key-input-settings-client-id')[0]->isVisible());
    $this->assertTrue($this->cssSelect('#edit-key-input-settings-client-secret')[0]->isVisible());

    // Test the form is disabled without a password.
    $this->assertTrue($this->cssSelect('input[data-drupal-selector="edit-test-connection-submit"]')[0]->hasAttribute('disabled'));
    $this->assertTrue($this->cssSelect('input[data-drupal-selector="edit-submit"]')[0]->hasAttribute('disabled'));

    // Set the password.
    $page->fillField('Password', $active_password);

    // Make sure the form is now enabled.
    $this->assertFalse($this->cssSelect('input[data-drupal-selector="edit-test-connection-submit"]')[0]->hasAttribute('disabled'));
    $this->assertFalse($this->cssSelect('input[data-drupal-selector="edit-submit"]')[0]->hasAttribute('disabled'));

    // Test the connection with basic auth.
    $this->assertSendRequestMessage('.messages--status', 'Connection successful.');
    $this->assertEmpty($this->cssSelect('details[data-drupal-selector="edit-debug"]'));

    // Switch back to basic auth.
    $this->cssSelect('select[data-drupal-selector="edit-key-input-settings-auth-type"]')[0]->setValue('basic');
    $page->fillField('Password', $active_password);
    // Make sure the form is still enabled.
    $this->assertFalse($this->cssSelect('input[data-drupal-selector="edit-test-connection-submit"]')[0]->hasAttribute('disabled'));
    $this->assertFalse($this->cssSelect('input[data-drupal-selector="edit-submit"]')[0]->hasAttribute('disabled'));

    // Test the connection with basic auth.
    $this->assertSendRequestMessage('.messages--status', 'Connection successful.');
    $this->assertEmpty($this->cssSelect('details[data-drupal-selector="edit-debug"]'));

    /* TEST INVALID PASSWORD */
    // Change the password.
    $page->fillField('Password', $this->randomString());
    $username = $active_key_type->getUsername($active_key);
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to Apigee Edge. The given username ({$username}) or password is incorrect. Error message: Unauthorized");
    $this->assertTrue($this->cssSelect('details[data-drupal-selector="edit-debug"]')[0]->isVisible());

    /* TEST INVALID ORG */
    $page->fillField('Password', $active_password);
    $random_org = $this->randomGenerator->word(16);
    $page->fillField('Organization', $random_org);
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to Apigee Edge. The given organization name ({$random_org}) is incorrect. Error message: Forbidden");
    $page->fillField('Organization', $active_org);
    $this->assertTrue($this->cssSelect('details[data-drupal-selector="edit-debug"]')[0]->isVisible());

    /* TEST INVALID ENDPOINT */
    $page->fillField('Password', $active_password);
    $invalid_domain = "{$this->randomGenerator->word(16)}.example.com";
    $page->fillField('Apigee Edge endpoint', "http://{$invalid_domain}/");
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to Apigee Edge. The given endpoint (http://{$invalid_domain}/) is incorrect or something is wrong with the connection. Error message: cURL error 6: Could not resolve host: {$invalid_domain} (see http://curl.haxx.se/libcurl/c/libcurl-errors.html)");
    $this->assertTrue($this->cssSelect('details[data-drupal-selector="edit-debug"]')[0]->isVisible());
    // Clear the endpoint field.
    $page->fillField('Apigee Edge endpoint', '');

    /* TEST INVALID AUTH SERVER */
    // Switch to oauth.
    $this->cssSelect('select[data-drupal-selector="edit-key-input-settings-auth-type"]')[0]->setValue('oauth');
    // Set the correct password.
    $page->fillField('Password', $active_password);
    $invalid_domain = "{$this->randomGenerator->word(16)}.example.com";
    $page->fillField('Authorization server', "http://{$invalid_domain}/");
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to the OAuth authorization server. The given authorization server (http://{$invalid_domain}/) is incorrect or something is wrong with the connection. Error message: cURL error 6: Could not resolve host: {$invalid_domain} (see http://curl.haxx.se/libcurl/c/libcurl-errors.html)");
    $page->fillField('Authorization server', "");

    /* TEST INVALID CLIENT SECRET */
    $page->fillField('Password', $active_password);
    // Set the client secret to a random value.
    $page->fillField('Client secret', $this->randomGenerator->word(16));
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to the OAuth authorization server. The given username ({$active_username}) or password or client ID (edgecli) or client secret is incorrect. Error message: {\"error\":\"unauthorized\",\"error_description\":\"Bad credentials\"}");
    $page->fillField('Client secret', '');

    /* TEST INVALID CLIENT ID */
    $page->fillField('Password', $active_password);
    // Set the client id to a random value.
    $client_id = $this->randomGenerator->word(8);
    $page->fillField('Client ID', $client_id);
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to the OAuth authorization server. The given username ({$active_username}) or password or client ID ({$client_id}) or client secret is incorrect. Error message: {\"error\":\"unauthorized\",\"error_description\":\"Bad credentials\"}");
    $page->fillField('Client ID', '');
  }

  /**
   * Test an connection settings.
   *
   * @param string $message_selector
   *   Either `.messages--error` or `.messages--error`.
   * @param string $message
   *   The error or status message.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ElementTextException
   */
  public function assertSendRequestMessage($message_selector, $message) {
    $web_assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Press the send request button.
    $page->pressButton('Send request');
    $web_assert->waitForElementVisible('css', '.ajax-progress.ajax-progress-throbber');
    $web_assert->elementTextContains('css', '.ajax-progress.ajax-progress-throbber', 'Waiting for response...');

    // Wait for the test to complete.
    $web_assert->assertWaitOnAjaxRequest(30000);
    $web_assert->elementTextContains('css', $message_selector, $message);
  }

}
