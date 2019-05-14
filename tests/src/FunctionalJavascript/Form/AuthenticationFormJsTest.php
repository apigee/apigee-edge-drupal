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
use Drupal\apigee_edge_test\EventSubscriber\MockApiRequestSubscriber\ApiResponseFromState;
use Drupal\Core\Url;
use Drupal\key\Entity\Key;
use Drupal\Tests\apigee_edge\FunctionalJavascript\ApigeeEdgeFunctionalJavascriptTestBase;
use GuzzleHttp\Psr7\Response;

/**
 * Apigee Edge API credentials, authentication form, key integration test.
 *
 * @group apigee_edge
 * @group apigee_edge_javascript
 */
class AuthenticationFormJsTest extends ApigeeEdgeFunctionalJavascriptTestBase {

  /**
   * Valid username.
   *
   * @var string
   */
  private $username;

  /**
   * Valid password.
   *
   * @var string
   */
  private $password;

  /**
   * Valid organization.
   *
   * @var string
   */
  private $organization;

  /**
   * Valid endpoint.
   *
   * @var string
   */
  private $endpoint;

  /**
   * The API response from State mock API request subscriber.
   *
   * @var \Drupal\apigee_edge_test\EventSubscriber\MockApiRequestSubscriber\ApiResponseFromState
   */
  private $apiResponseFromState;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Save valid credentials for later use.
    /** @var \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface $test_key_type */
    $test_key = Key::load($this->config(AuthenticationForm::CONFIG_NAME)->get('active_key'));
    $test_key_type = $test_key->getKeyType();
    $this->username = $test_key_type->getUsername($test_key);
    $this->password = $test_key_type->getPassword($test_key);
    $this->organization = $test_key_type->getOrganization($test_key);
    $this->endpoint = $test_key_type->getEndpoint($test_key);
    // Restore the default HTTP timeout set by the testing module because
    // we would like to run a test that tries to connect to an invalid
    // endpoint and we should not wait 6 minutes for the result.
    $this->config('apigee_edge.client')->set('http_client_timeout', 30)->save();
    $this->apiResponseFromState = $this->container->get('apigee_edge_test.mock_api_request_subscriber.api_response_from_state');
  }

  /**
   * Tests the Authentication form.
   */
  public function testAuthenticationForm(): void {
    $web_assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Test the authentication form using the default key stored by environment
    // variable key provider.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));
    $this->assertSession()->pageTextContains("The selected key provider does not accept a value. See the provider's description for instructions on how and where to store the key value.");
    $this->assertSession()->pageTextContains('Send request using the active authentication key.');
    $this->assertSendRequestMessage('.messages--status', 'Connection successful.');
    $web_assert->elementNotExists('css', 'details[data-drupal-selector="edit-debug"]');

    $this->validateForm([$this, 'visitAuthenticationForm']);

    // Validate that the form actually saved the valid credentials (DRUP-734).
    $this->visitAuthenticationForm();
    // Fill in the form with valid credentials again.
    $page->fillField('Username', $this->username);
    $page->fillField('Password', $this->password);
    $page->fillField('Organization', $this->organization);
    $page->fillField('Apigee Edge endpoint', $this->endpoint);
    $this->pressKeySaveButton('op', [$this, 'visitAuthenticationForm']);
    $web_assert->fieldValueEquals('Organization', $this->organization);
    $web_assert->fieldValueEquals('Username', $this->username);
    $web_assert->fieldValueEquals('Password', $this->password);
    $web_assert->fieldValueEquals('Apigee Edge endpoint', $this->endpoint);
  }

  /**
   * Tests the Key add form.
   *
   * We assume that if the Authentication form and the Key add form test passed
   * then the Key edit form also works correctly, because the Authentication
   * form is a customized Key edit form.
   */
  public function testKeyAddForm(): void {
    $web_assert = $this->assertSession();

    // Test the authentication form using the default key stored by environment
    // variable key provider.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet(Url::fromRoute('entity.key.add_form'));

    // The default Key type is "Authentication" so the "Send request" button
    // should not be visible.
    $web_assert->elementNotExists('css', 'input[name="test_connection"]');

    $this->cssSelect('select[name="key_type"]')[0]->setValue('apigee_auth');
    // The "Send request" button now should appear.
    $web_assert->waitForElementVisible('css', 'input[name="test_connection"]');

    // The "Send request" button now should not appear if Key entity form
    // customization is disabled.
    $this->config('apigee_edge.dangerzone')->set('do_not_alter_key_entity_forms', TRUE)->save();
    $this->drupalGet(Url::fromRoute('entity.key.add_form'));
    $this->cssSelect('select[name="key_type"]')[0]->setValue('apigee_auth');
    $web_assert->assertWaitOnAjaxRequest();
    $web_assert->elementNotExists('css', 'input[name="test_connection"]');
    // Revert the config change.
    $this->config('apigee_edge.dangerzone')->set('do_not_alter_key_entity_forms', FALSE)->save();

    $this->validateForm([$this, 'visitKeyAddForm']);
  }

  /**
   * Visits the Authentication form for testing.
   */
  protected function visitAuthenticationForm(): void {
    if ($this->loggedInUser->id() !== $this->rootUser) {
      $this->drupalLogin($this->rootUser);
    }
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));
  }

  /**
   * Visits the Key add form for testing.
   */
  protected function visitKeyAddForm(): void {
    $web_assert = $this->assertSession();
    $page = $this->getSession()->getPage();
    if ($this->loggedInUser->id() !== $this->rootUser) {
      $this->drupalLogin($this->rootUser);
    }
    $this->drupalGet(Url::fromRoute('entity.key.add_form'));
    // Key name is required.
    $page->fillField('Key name', $this->randomMachineName());
    $this->cssSelect('select[name="key_type"]')[0]->setValue('apigee_auth');
    // The "Send request" button now should appear again.
    $web_assert->waitForElementVisible('css', 'input[name="test_connection"]');
    $this->cssSelect('select[name="key_provider"]')[0]->setValue('apigee_edge_private_file');
    $web_assert->waitForElementVisible('css', 'key_input_settings[organization]');
  }

  /**
   * Validates the visited form.
   *
   * @param callable $visitFormAsAdmin
   *   The function that visits the form as an admin user that we would like
   *   to validate.
   */
  protected function validateForm(callable $visitFormAsAdmin): void {
    $web_assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Unset private file path and invalidate the active key.
    $settings['settings']['file_private_path'] = (object) [
      'value' => '',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
    $this->invalidateKey();

    // Ensure that the private file key provider is the default on the
    // Authentication form and form detects the problem caused by the
    // missing private filesystem configuration.
    $visitFormAsAdmin();
    $this->assertSession()->pageTextContains('The requirements of the selected Apigee Edge: Private File key provider are not fulfilled. Fix errors described below or change the key provider.');
    $this->assertSession()->pageTextContains('Private filesystem has not been configured yet. Learn more');

    // Set private file path.
    $settings['settings']['file_private_path'] = (object) [
      'value' => "{$this->siteDirectory}/private",
      'required' => TRUE,
    ];
    $this->writeSettings($settings);

    // Reload the page, the key input form should be visible.
    $visitFormAsAdmin();

    // Make sure the default fields are visible and empty.
    $web_assert->fieldValueEquals('Authentication type', 'basic');
    $web_assert->fieldValueEquals('Username', '');
    $web_assert->fieldValueEquals('Password', '');
    $web_assert->fieldValueEquals('Organization', '');
    $web_assert->fieldValueEquals('Apigee Edge endpoint', '');

    // Make sure the oauth fields are hidden.
    $this->assertFalse($this->cssSelect('input[name="key_input_settings[authorization_server]"]')[0]->isVisible());
    $this->assertFalse($this->cssSelect('input[name="key_input_settings[client_id]"]')[0]->isVisible());
    $this->assertFalse($this->cssSelect('input[name="key_input_settings[client_secret]"]')[0]->isVisible());

    // Test the connection with basic auth.
    $page->fillField('Username', $this->username);
    $page->fillField('Password', $this->password);
    $page->fillField('Organization', $this->organization);
    $page->fillField('Apigee Edge endpoint', $this->endpoint);
    $this->assertSession()->pageTextContains('Send request using the given API credentials.');
    $this->assertSendRequestMessage('.messages--status', 'Connection successful.');
    $web_assert->elementNotExists('css', 'details[data-drupal-selector="edit-debug"]');

    // Switch to oauth.
    $this->cssSelect('select[name="key_input_settings[auth_type]"]')[0]->setValue('oauth');
    // Make sure the oauth fields are visible.
    $this->assertTrue($this->cssSelect('input[name="key_input_settings[authorization_server]"]')[0]->isVisible());
    $this->assertTrue($this->cssSelect('input[name="key_input_settings[client_id]"]')[0]->isVisible());
    $this->assertTrue($this->cssSelect('input[name="key_input_settings[client_secret]"]')[0]->isVisible());

    // Make sure that test connection is disabled without a password.
    $page->fillField('Password', '');
    $this->assertTrue($this->cssSelect('input[name="test_connection"]')[0]->hasAttribute('disabled'));

    // Make sure that test connection is now enabled.
    $page->fillField('Password', $this->password);
    $this->assertFalse($this->cssSelect('input[name="test_connection"]')[0]->hasAttribute('disabled'));

    // Test the connection with oauth.
    $this->assertSendRequestMessage('.messages--status', 'Connection successful.');
    $web_assert->elementNotExists('css', 'details[data-drupal-selector="edit-debug"]');
    // Make sure the token file has not been left behind.
    $token_file_path = $this->container->get('file_system')->realpath(OauthTokenFileStorage::DEFAULT_DIRECTORY . '/oauth.dat');
    $this->assertFileNotExists($token_file_path);

    // Switch back to basic auth.
    $this->cssSelect('select[name="key_input_settings[auth_type]"]')[0]->setValue('basic');

    // Test the connection with basic auth.
    $this->assertSendRequestMessage('.messages--status', 'Connection successful.');
    $web_assert->elementNotExists('css', 'details[data-drupal-selector="edit-debug"]');
    // Press the Save/Save configuration button and save valid credentials.
    $this->pressKeySaveButton('op', $visitFormAsAdmin);

    // EDGE CASE 1
    // - Save valid credentials for ORG A.
    // - Fill in the form with valid credentials for ORG B.
    // - Test connection.
    // - Save form with valid credentials for ORG B.
    // Generate ORG B credentials that are actually invalid credentials but we
    // will mock them as if they would be valid.
    $random_username = $this->randomMachineName() . '@example.com';
    $random_pass = $this->randomMachineName();
    $random_org = $this->randomMachineName();
    $random_endpoint = "http://{$this->randomGenerator->word(16)}.example.com";
    $page->fillField('Username', $random_username);
    $page->fillField('Password', $random_pass);
    $page->fillField('Organization', $random_org);
    $page->fillField('Apigee Edge endpoint', $random_endpoint);
    // Queue a new HTTP 200 response for (invalid) ORG B credentials before
    // we would press the "Test connection" button.
    $this->apiResponseFromState->queueResponse(new Response(200, ['Content-Type' => 'application/json'], '{}'));
    // Capture mocked HTTP requests from the state storege.
    $settings['settings'][ApiResponseFromState::SETTINGS_CAPTURE_REQUESTS] = (object) ['value' => TRUE, 'required' => TRUE];
    $this->writeSettings($settings);
    $this->assertSendRequestMessage('.messages--status', 'Connection successful.');
    /** @var \Psr\Http\Message\RequestInterface $mocked_request */
    $mocked_requests = $this->apiResponseFromState->getMockedRequests();
    $mocked_request = reset($mocked_requests);
    // Make sure that all sent API credentials belongs to ORG B.
    $this->assertStringStartsWith($random_endpoint . '/organizations/' . $random_org, (string) $mocked_request->getUri());
    $this->assertEquals(sprintf('Basic %s', base64_encode(sprintf('%s:%s', $random_username, $random_pass))), $mocked_request->getHeaderLine('Authorization'));
    // Disable capturing mocked API request as it is not necessary anymore.
    $settings['settings'][ApiResponseFromState::SETTINGS_CAPTURE_REQUESTS] = (object) ['value' => FALSE, 'required' => TRUE];
    $this->writeSettings($settings);
    // Queue a new HTTP 200 response for (invalid) ORG B credentials before
    // we would save the form.
    $this->apiResponseFromState->queueResponse(new Response(200, ['Content-Type' => 'application/json'], '{}'));
    // Press the Save/Save configuration button and save invalid credentials.
    $this->pressKeySaveButton('op', $visitFormAsAdmin);
    // END OF EDGE CASE 1.

    // Fill in the form with valid credentials again.
    $page->fillField('Username', $this->username);
    $page->fillField('Password', $this->password);
    $page->fillField('Organization', $this->organization);
    $page->fillField('Apigee Edge endpoint', $this->endpoint);

    // Test invalid password.
    $random_pass = $this->randomString();
    $page->fillField('Password', $random_pass);
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to Apigee Edge. The given username ({$this->username}) or password is incorrect. Error message: ");
    // TODO Re-add this assert later. It had to be disabled because of a
    // regression bug in the Apigee Edge for Public Cloud 19.03.01 release. If
    // valid organization name and username provided with an invalid password
    // the MGMT server returns HTTP 500 with an error instead of HTTP 401.
    // $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', 'HTTP/1.1 401 Unauthorized');.
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '***credentials***');
    $web_assert->elementNotContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', $random_pass);
    $page->fillField('Password', $this->password);

    // Test invalid organization.
    $random_org = $this->randomGenerator->word(16);
    $page->fillField('Organization', $random_org);
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to Apigee Edge. The given organization name ({$random_org}) is incorrect. Error message: ");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', 'HTTP/1.1 403 Forbidden');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', "\"organization\": \"{$random_org}\"");
    $page->fillField('Organization', $this->organization);

    // Test invalid endpoint.
    $invalid_domain = "{$this->randomGenerator->word(16)}.example.com";
    $page->fillField('Apigee Edge endpoint', "http://{$invalid_domain}/");
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to Apigee Edge. The given endpoint (http://{$invalid_domain}/) is incorrect or something is wrong with the connection. Error message: ");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', "\"endpoint\": \"http:\/\/{$invalid_domain}\/\"");
    $web_assert->fieldValueEquals('Apigee Edge endpoint', "http://{$invalid_domain}/");
    $page->fillField('Apigee Edge endpoint', '');

    // Test invalid authorization server.
    $this->cssSelect('select[data-drupal-selector="edit-key-input-settings-auth-type"]')[0]->setValue('oauth');
    $invalid_domain = "{$this->randomGenerator->word(16)}.example.com";
    $page->fillField('Authorization server', "http://{$invalid_domain}/");
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to the OAuth authorization server. The given authorization server (http://{$invalid_domain}/) is incorrect or something is wrong with the connection. Error message: ");
    $web_assert->fieldValueEquals('Authorization server', "http://{$invalid_domain}/");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"auth_type": "oauth"');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', "\"authorization_server\": \"http:\/\/{$invalid_domain}\/\"");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"client_id": "edgecli"');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"client_secret": "edgeclisecret"');
    $page->fillField('Authorization server', '');

    // Test invalid client secret.
    $random_secret = $this->randomGenerator->word(16);
    $page->fillField('Client secret', $random_secret);
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to the OAuth authorization server. The given username ({$this->username}) or password or client ID (edgecli) or client secret is incorrect. Error message: ");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"authorization_server": "https:\/\/login.apigee.com\/oauth\/token"');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"client_id": "edgecli"');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"client_secret": "***client-secret***"');
    $web_assert->elementNotContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', $random_secret);
    $page->fillField('Client secret', '');

    // Test invalid client id.
    $client_id = $this->randomGenerator->word(8);
    $page->fillField('Client ID', $client_id);
    $this->assertSendRequestMessage('.messages--error', "Failed to connect to the OAuth authorization server. The given username ({$this->username}) or password or client ID ({$client_id}) or client secret is incorrect. Error message: ");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"authorization_server": "https:\/\/login.apigee.com\/oauth\/token"');
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', "\"client_id\": \"{$client_id}\"");
    $web_assert->elementContains('css', 'textarea[data-drupal-selector="edit-debug-text"]', '"client_secret": "edgeclisecret"');
    $page->fillField('Client ID', '');
  }

  /**
   * Clicks on the key save button and revisits the form.
   *
   * @param string $locator
   *   Button id, value or alt.
   * @param callable $visitFormAsAdmin
   *   Callback function that revisits the form as admin.
   */
  private function pressKeySaveButton(string $locator, callable $visitFormAsAdmin): void {
    $web_assert = $this->assertSession();
    $page = $this->getSession()->getPage();
    $page->pressButton($locator);
    $web_assert->elementTextContains('css', '.messages--status', 'Connection successful.');
    // Because Key add/edit form redirects the user to the Key entity listing
    // page on success therefore we have to re-visit the form again.
    $visitFormAsAdmin();
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
    $this->assertNotNull($web_assert->waitForElementVisible('css', '.ajax-progress.ajax-progress-throbber', 30000));

    // Wait for the test to complete.
    $web_assert->assertWaitOnAjaxRequest(30000);
    $web_assert->elementTextContains('css', $message_selector, $message);
  }

}
