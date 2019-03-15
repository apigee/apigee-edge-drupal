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
use Drupal\apigee_edge\OauthTokenFileStorage;
use Drupal\Core\Url;
use Drupal\key\Entity\Key;
use Drupal\Tests\apigee_edge\FunctionalJavascript\ApigeeEdgeFunctionalJavascriptTestBase;

/**
 * Apigee Edge API credentials, authentication form, key integration test.
 *
 * @group apigee_edge
 * @group apigee_edge_javascript
 */
class AuthenticationFormJsTest extends ApigeeEdgeFunctionalJavascriptTestBase {

  /**
   * Tests Apigee Edge key types, key providers and authentication form.
   */
  public function testAuthenticationForm() {
    $web_assert = $this->assertSession();
    // Save valid credentials for later use.
    /** @var \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface $test_key_type */
    $test_key = Key::load($this->config(AuthenticationForm::CONFIG_NAME)->get('active_key'));
    $test_key_type = $test_key->getKeyType();
    $username = $test_key_type->getUsername($test_key);
    $password = $test_key_type->getPassword($test_key);
    $organization = $test_key_type->getOrganization($test_key);

    // Test the authentication form using the default key stored by environment
    // variable key provider.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));
    $this->assertSession()->pageTextContains('The selected key provider does not accept a value. See the provider\'s description for instructions on how and where to store the key value.');
    $this->assertSession()->pageTextContains('Send request using the active authentication key.');
    $this->assertSendRequestMessage('.messages--status', 'Connection successful.');
    $web_assert->elementNotExists('css', 'details[data-drupal-selector="edit-debug"]');

    // Unset private file path and invalidate the active key.
    $settings['settings']['file_private_path'] = (object) [
      'value' => '',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
    $this->invalidateKey();

    // Ensure that the authentication form creates the new default key with
    // private file key provider and detects the problem.
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));
    $this->assertSession()->pageTextContains('The requirements of the selected key provider (Apigee Edge: Private File) are not fulfilled. Fix errors described below or change the active key\'s provider.');
    $this->assertSession()->pageTextContains('Private filesystem has not been configured yet. Learn more');

    // Set private file path.
    $settings['settings']['file_private_path'] = (object) [
      'value' => "{$this->siteDirectory}/private",
      'required' => TRUE,
    ];
    $this->writeSettings($settings);

    // Reload the page, the key input form should be visible.
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));
    $page = $this->getSession()->getPage();

    // Make sure the default fields are visible and empty.
    $web_assert->fieldValueEquals('Authentication type', 'basic');
    $web_assert->fieldValueEquals('Username', '');
    $web_assert->fieldValueEquals('Password', '');
    $web_assert->fieldValueEquals('Organization', '');
    $web_assert->fieldValueEquals('Apigee Edge endpoint', '');

    // Make sure the oauth fields are hidden.
    $this->assertFalse($this->cssSelect('#edit-key-input-settings-authorization-server')[0]->isVisible());
    $this->assertFalse($this->cssSelect('#edit-key-input-settings-client-id')[0]->isVisible());
    $this->assertFalse($this->cssSelect('#edit-key-input-settings-client-secret')[0]->isVisible());

    // Test the connection with basic auth.
    $page->fillField('Username', $username);
    $page->fillField('Password', $password);
    $page->fillField('Organization', $organization);
    $this->assertSession()->pageTextContains('Send request using the given API credentials.');
    $this->assertSendRequestMessage('.messages--status', 'Connection successful.');
    $web_assert->elementNotExists('css', 'details[data-drupal-selector="edit-debug"]');

    // Switch to oauth.
    $this->cssSelect('#edit-key-input-settings-auth-type')[0]->setValue('oauth');
    // Make sure the oauth fields are visible.
    $this->assertTrue($this->cssSelect('#edit-key-input-settings-authorization-server')[0]->isVisible());
    $this->assertTrue($this->cssSelect('#edit-key-input-settings-client-id')[0]->isVisible());
    $this->assertTrue($this->cssSelect('#edit-key-input-settings-client-secret')[0]->isVisible());

    // Make sure the form is disabled without a password.
    $page->fillField('Password', '');
    $this->assertTrue($this->cssSelect('input[data-drupal-selector="edit-test-connection-submit"]')[0]->hasAttribute('disabled'));
    $this->assertTrue($this->cssSelect('input[data-drupal-selector="edit-submit"]')[0]->hasAttribute('disabled'));

    // Make sure the form is now enabled.
    $page->fillField('Password', $password);
    $this->assertFalse($this->cssSelect('input[data-drupal-selector="edit-test-connection-submit"]')[0]->hasAttribute('disabled'));
    $this->assertFalse($this->cssSelect('input[data-drupal-selector="edit-submit"]')[0]->hasAttribute('disabled'));

    // Test the connection with oauth.
    $this->assertSendRequestMessage('.messages--status', 'Connection successful.');
    $web_assert->elementNotExists('css', 'details[data-drupal-selector="edit-debug"]');

    // Switch back to basic auth.
    $this->cssSelect('select[data-drupal-selector="edit-key-input-settings-auth-type"]')[0]->setValue('basic');
    // Make sure the form is still enabled.
    $this->assertFalse($this->cssSelect('input[data-drupal-selector="edit-test-connection-submit"]')[0]->hasAttribute('disabled'));
    $this->assertFalse($this->cssSelect('input[data-drupal-selector="edit-submit"]')[0]->hasAttribute('disabled'));

    // Test the connection with basic auth.
    $this->assertSendRequestMessage('.messages--status', 'Connection successful.');
    $web_assert->elementNotExists('css', 'details[data-drupal-selector="edit-debug"]');
    // Make sure the token file is removed when switching to basic auth.
    $token_file_path = $this->container->get('file_system')->realpath(OauthTokenFileStorage::DEFAULT_DIRECTORY . '/oauth.dat');
    $this->assertTrue(file_exists($token_file_path));
    $page->pressButton('Save configuration');
    $this->assertFalse(file_exists($token_file_path));

    // Test invalid password.
    $random_pass = $this->randomString();
    $page->fillField('Password', $random_pass);
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to Apigee Edge. The given username ({$username}) or password is incorrect. Error message: Unauthorized");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', 'HTTP/1.1 401 Unauthorized');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '***credentials***');
    $web_assert->elementNotContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', $random_pass);
    $page->fillField('Password', $password);

    // Test invalid organization.
    $random_org = $this->randomGenerator->word(16);
    $page->fillField('Organization', $random_org);
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to Apigee Edge. The given organization name ({$random_org}) is incorrect. Error message: Forbidden");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', 'HTTP/1.1 403 Forbidden');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', "\"organization\": \"{$random_org}\"");
    $page->fillField('Organization', $organization);

    // Test invalid endpoint.
    $invalid_domain = "{$this->randomGenerator->word(16)}.example.com";
    $page->fillField('Apigee Edge endpoint', "http://{$invalid_domain}/");
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to Apigee Edge. The given endpoint (http://{$invalid_domain}/) is incorrect or something is wrong with the connection. Error message: cURL error 6: Could not resolve host: {$invalid_domain} (see http://curl.haxx.se/libcurl/c/libcurl-errors.html)");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', "\"endpoint\": \"http:\/\/{$invalid_domain}\/\"");
    $web_assert->fieldValueEquals('Apigee Edge endpoint', "http://{$invalid_domain}/");
    $page->fillField('Apigee Edge endpoint', '');

    // Test invalid authorization server.
    $this->cssSelect('select[data-drupal-selector="edit-key-input-settings-auth-type"]')[0]->setValue('oauth');
    $invalid_domain = "{$this->randomGenerator->word(16)}.example.com";
    $page->fillField('Authorization server', "http://{$invalid_domain}/");
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to the OAuth authorization server. The given authorization server (http://{$invalid_domain}/) is incorrect or something is wrong with the connection. Error message: cURL error 6: Could not resolve host: {$invalid_domain} (see http://curl.haxx.se/libcurl/c/libcurl-errors.html)");
    $web_assert->fieldValueEquals('Authorization server', "http://{$invalid_domain}/");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"auth_type": "oauth"');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', "\"authorization_server\": \"http:\/\/{$invalid_domain}\/\"");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"client_id": "edgecli"');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"client_secret": "edgeclisecret"');
    $page->fillField('Authorization server', '');

    // Test invalid client secret.
    $random_secret = $this->randomGenerator->word(16);
    $page->fillField('Client secret', $random_secret);
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to the OAuth authorization server. The given username ({$username}) or password or client ID (edgecli) or client secret is incorrect. Error message: {\"error\":\"unauthorized\",\"error_description\":\"Bad credentials\"}");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"authorization_server": "https:\/\/login.apigee.com\/oauth\/token"');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"client_id": "edgecli"');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"client_secret": "***client-secret***"');
    $web_assert->elementNotContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', $random_secret);
    $page->fillField('Client secret', '');

    // Test invalid client id.
    $client_id = $this->randomGenerator->word(8);
    $page->fillField('Client ID', $client_id);
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to the OAuth authorization server. The given username ({$username}) or password or client ID ({$client_id}) or client secret is incorrect. Error message: {\"error\":\"unauthorized\",\"error_description\":\"Bad credentials\"}");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"authorization_server": "https:\/\/login.apigee.com\/oauth\/token"');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', "\"client_id\": \"{$client_id}\"");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"client_secret": "edgeclisecret"');
    $page->fillField('Client ID', '');
  }

  /**
   * Tests send request functionality.
   *
   * @param string $message_selector
   *   Either `.messages--error` or `.messages--error`.
   * @param string $message
   *   The error or status message.
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
