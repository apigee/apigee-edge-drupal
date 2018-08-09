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

namespace Drupal\Tests\apigee_edge\FunctionalJavascript;

use Apigee\Edge\HttpClient\Plugin\Authentication\Oauth;
use Drupal\apigee_edge\Plugin\KeyType\OauthKeyType;
use Drupal\Core\Url;
use Drupal\key\Entity\Key;

/**
 * Apigee Edge API credentials, authentication form, key integration test.
 *
 * @group apigee_edge
 * @group apigee_edge_javascript
 */
class AuthenticationTest extends ApigeeEdgeFunctionalJavascriptTestBase {

  const NO_AVAILABLE_BASIC_AUTH_KEY = 'There is no available basic authentication key for connecting to Apigee Edge.';

  const NO_AVAILABLE_OAUTH_KEY = 'There is no available OAuth key for connecting to Apigee Edge.';

  const NO_AVAILABLE_OAUTH_TOKEN_KEY = 'There is no available OAuth token key for connecting to Apigee Edge.';

  const DEBUG_INFORMATION_TITLE = 'Debug information';

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
   */
  public function testAuthentication() {
    // Delete pre-defined test key.
    Key::load('test')->delete();
    $this->assertEmpty($this->container->get('state')->get('apigee_edge.auth')['active_key']);
    $this->assertEmpty($this->container->get('state')->get('apigee_edge.auth')['active_key_oauth_token']);

    // Create a key entity using key's default type and provider, then visit
    // authentication form and ensure that there are no available Apigee Edge
    // keys.
    Key::create([
      'id' => 'default_key',
    ])->save();
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));
    $this->assertSession()->pageTextContains(self::NO_AVAILABLE_BASIC_AUTH_KEY);
    $this->assertSession()->pageTextNotContains(self::NO_AVAILABLE_OAUTH_KEY);
    $this->assertSession()->pageTextNotContains(self::NO_AVAILABLE_OAUTH_TOKEN_KEY);
    $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_oauth');
    $this->assertSession()->pageTextContains(self::NO_AVAILABLE_OAUTH_KEY);
    $this->assertSession()->pageTextContains(self::NO_AVAILABLE_OAUTH_TOKEN_KEY);
    $this->assertSession()->pageTextNotContains(self::NO_AVAILABLE_BASIC_AUTH_KEY);

    // Basic authentication, environment variables.
    $this->createKey('key_basic_auth_env_variables', 'apigee_edge_basic_auth', 'apigee_edge_environment_variables');
    // Basic authentication, private file.
    $this->createKey('key_basic_auth_private_file', 'apigee_edge_basic_auth', 'apigee_edge_private_file', $this->validCredentials);

    // Check authentication form.
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));
    $this->assertSession()->pageTextContains('key_basic_auth_env_variables');
    $this->assertSession()->pageTextContains('key_basic_auth_private_file');
    $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_oauth');
    $this->assertSession()->pageTextContains(self::NO_AVAILABLE_OAUTH_KEY);
    $this->assertSession()->pageTextContains(self::NO_AVAILABLE_OAUTH_TOKEN_KEY);
    $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_basic_auth');

    // OAuth token key, private file.
    $this->createKey('key_oauth_token', 'apigee_edge_oauth_token', 'apigee_edge_private_file');
    // Check authentication form.
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));
    $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_oauth');
    $this->assertSession()->pageTextNotContains('key_oauth_token');
    $this->assertSession()->pageTextNotContains(self::NO_AVAILABLE_OAUTH_TOKEN_KEY);
    $this->assertSession()->pageTextContains(self::NO_AVAILABLE_OAUTH_KEY);

    // OAuth, environment variables.
    $this->createKey('key_oauth_env_variables', 'apigee_edge_oauth', 'apigee_edge_environment_variables');
    // OAuth, private file.
    $this->createKey('key_oauth_private_file', 'apigee_edge_oauth', 'apigee_edge_private_file', $this->validCredentials);
    // Check authentication form.
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));
    $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_oauth');
    $this->assertSession()->pageTextContains('key_oauth_token');
    $this->assertSession()->pageTextContains('key_oauth_env_variables');
    $this->assertSession()->pageTextContains('key_oauth_private_file');

    // Test connection using properly set keys.
    $this->assertKeyTestConnection('key_basic_auth_env_variables');
    $this->assertKeyTestConnection('key_basic_auth_private_file');
    $this->assertKeyTestConnection('key_oauth_env_variables', 'key_oauth_token');
    $this->assertKeyTestConnection('key_oauth_private_file', 'key_oauth_token');
    // Save properly set keys.
    $this->assertKeySave('key_basic_auth_env_variables');
    $this->assertKeySave('key_basic_auth_private_file');
    $this->assertKeySave('key_oauth_env_variables', 'key_oauth_token');
    $this->assertKeySave('key_oauth_private_file', 'key_oauth_token');

    // Create and test keys with invalid username.
    $invalidCredentials = $this->validCredentials;
    $invalidCredentials['username'] = "{$this->randomGenerator->word(8)}@example.com";
    $this->createKey('key_basic_auth_private_file_invalid_username', 'apigee_edge_basic_auth', 'apigee_edge_private_file', $invalidCredentials);
    $this->createKey('key_oauth_private_file_invalid_username', 'apigee_edge_oauth', 'apigee_edge_private_file', $invalidCredentials);
    $this->assertKeyTestConnection('key_basic_auth_private_file_invalid_username', '', "Failed to connect to Apigee Edge. The given username ({$invalidCredentials['username']}) or password is incorrect. Error message: Unauthorized.");
    $this->assertKeyTestConnection('key_oauth_private_file_invalid_username', 'key_oauth_token', "Failed to connect to the OAuth authorization server. The given username ({$invalidCredentials['username']}) or password is incorrect. Error message: {\"error\":\"unauthorized\",\"error_description\":\"Bad credentials\"}.");
    $this->assertKeySave('key_basic_auth_private_file_invalid_username', '', "Failed to connect to Apigee Edge. The given username ({$invalidCredentials['username']}) or password is incorrect. Error message: Unauthorized.");
    $this->assertKeySave('key_oauth_private_file_invalid_username', 'key_oauth_token', "Failed to connect to the OAuth authorization server. The given username ({$invalidCredentials['username']}) or password is incorrect. Error message: {\"error\":\"unauthorized\",\"error_description\":\"Bad credentials\"}.");

    // Create and test keys with invalid organization.
    $invalidCredentials = $this->validCredentials;
    $invalidCredentials['organization'] = $this->randomGenerator->word(8);
    $this->createKey('key_basic_auth_private_file_invalid_organization', 'apigee_edge_basic_auth', 'apigee_edge_private_file', $invalidCredentials);
    $this->createKey('key_oauth_private_file_invalid_organization', 'apigee_edge_oauth', 'apigee_edge_private_file', $invalidCredentials);
    $this->assertKeyTestConnection('key_basic_auth_private_file_invalid_organization', '', "Failed to connect to Apigee Edge. The given organization name ({$invalidCredentials['organization']}) is incorrect. Error message: Forbidden.");
    $this->assertKeyTestConnection('key_oauth_private_file_invalid_organization', 'key_oauth_token', "Failed to connect to Apigee Edge. The given organization name ({$invalidCredentials['organization']}) is incorrect. Error message: Forbidden.");
    $this->assertKeySave('key_basic_auth_private_file_invalid_organization', '', "Failed to connect to Apigee Edge. The given organization name ({$invalidCredentials['organization']}) is incorrect. Error message: Forbidden.");
    $this->assertKeySave('key_oauth_private_file_invalid_organization', 'key_oauth_token', "Failed to connect to Apigee Edge. The given organization name ({$invalidCredentials['organization']}) is incorrect. Error message: Forbidden.");

    // Create and test keys with invalid endpoint.
    $invalidCredentials = $this->validCredentials;
    $invalidCredentials['endpoint'] = "https://{$this->randomGenerator->word(16)}.com";
    $host = substr($invalidCredentials['endpoint'], 8);
    $this->createKey('key_basic_auth_private_file_invalid_endpoint', 'apigee_edge_basic_auth', 'apigee_edge_private_file', $invalidCredentials);
    $this->createKey('key_oauth_private_file_invalid_endpoint', 'apigee_edge_oauth', 'apigee_edge_private_file', $invalidCredentials);
    $this->assertKeyTestConnection('key_basic_auth_private_file_invalid_endpoint', '', "Failed to connect to Apigee Edge. The given endpoint ({$invalidCredentials['endpoint']}) is incorrect or something is wrong with the connection. Error message: cURL error 6: Could not resolve host: {$host} (see http://curl.haxx.se/libcurl/c/libcurl-errors.html).");
    $this->assertKeyTestConnection('key_oauth_private_file_invalid_endpoint', 'key_oauth_token', "Failed to connect to Apigee Edge. The given endpoint ({$invalidCredentials['endpoint']}) is incorrect or something is wrong with the connection. Error message: cURL error 6: Could not resolve host: {$host} (see http://curl.haxx.se/libcurl/c/libcurl-errors.html).");
    $this->assertKeySave('key_basic_auth_private_file_invalid_endpoint', '', "Failed to connect to Apigee Edge. The given endpoint ({$invalidCredentials['endpoint']}) is incorrect or something is wrong with the connection. Error message: cURL error 6: Could not resolve host: {$host} (see http://curl.haxx.se/libcurl/c/libcurl-errors.html).");
    $this->assertKeySave('key_oauth_private_file_invalid_endpoint', 'key_oauth_token', "Failed to connect to Apigee Edge. The given endpoint ({$invalidCredentials['endpoint']}) is incorrect or something is wrong with the connection. Error message: cURL error 6: Could not resolve host: {$host} (see http://curl.haxx.se/libcurl/c/libcurl-errors.html).");

    // Create and test keys with invalid authorization server.
    $invalidCredentials = $this->validCredentials;
    $invalidCredentials['authorization_server'] = "https://{$this->randomGenerator->word(8)}.com";
    $host = substr($invalidCredentials['authorization_server'], 8);
    $this->createKey('key_oauth_private_file_invalid_authorization_server', 'apigee_edge_oauth', 'apigee_edge_private_file', $invalidCredentials);
    $this->assertKeyTestConnection('key_oauth_private_file_invalid_authorization_server', 'key_oauth_token', "Failed to connect to the OAuth authorization server. The given authorization server ({$invalidCredentials['authorization_server']}) is incorrect or something is wrong with the connection. Error message: cURL error 6: Could not resolve host: {$host} (see http://curl.haxx.se/libcurl/c/libcurl-errors.html).");
    $this->assertKeySave('key_oauth_private_file_invalid_authorization_server', 'key_oauth_token', "Failed to connect to the OAuth authorization server. The given authorization server ({$invalidCredentials['authorization_server']}) is incorrect or something is wrong with the connection. Error message: cURL error 6: Could not resolve host: {$host} (see http://curl.haxx.se/libcurl/c/libcurl-errors.html).");

    // Create and test keys with invalid client_secret.
    $invalidCredentials = $this->validCredentials;
    $invalidCredentials['client_secret'] = $this->randomGenerator->word(8);
    $this->createKey('key_oauth_private_file_invalid_client_secret', 'apigee_edge_oauth', 'apigee_edge_private_file', $invalidCredentials);
    $this->assertKeyTestConnection('key_oauth_private_file_invalid_client_secret', 'key_oauth_token', "Failed to connect to the OAuth authorization server. The given username ({$invalidCredentials['username']}) or password or client ID (edgecli) or client secret is incorrect. Error message: {\"error\":\"unauthorized\",\"error_description\":\"Bad credentials\"}.");
    $this->assertKeySave('key_oauth_private_file_invalid_client_secret', 'key_oauth_token', "Failed to connect to the OAuth authorization server. The given username ({$invalidCredentials['username']}) or password or client ID (edgecli) or client secret is incorrect. Error message: {\"error\":\"unauthorized\",\"error_description\":\"Bad credentials\"}.");

    // Create and test keys with invalid client_id.
    $invalidCredentials = $this->validCredentials;
    $invalidCredentials['client_id'] = $this->randomGenerator->word(8);
    $this->createKey('key_oauth_private_file_invalid_client_id', 'apigee_edge_oauth', 'apigee_edge_private_file', $invalidCredentials);
    $this->assertKeyTestConnection('key_oauth_private_file_invalid_client_id', 'key_oauth_token', "Failed to connect to the OAuth authorization server. The given username ({$invalidCredentials['username']}) or password or client ID ({$invalidCredentials['client_id']}) or client secret is incorrect. Error message: {\"error\":\"unauthorized\",\"error_description\":\"Bad credentials\"}.");
    $this->assertKeySave('key_oauth_private_file_invalid_client_id', 'key_oauth_token', "Failed to connect to the OAuth authorization server. The given username ({$invalidCredentials['username']}) or password or client ID ({$invalidCredentials['client_id']}) or client secret is incorrect. Error message: {\"error\":\"unauthorized\",\"error_description\":\"Bad credentials\"}.");

    // Create and test keys with too low timeouts.
    $this->createKey('key_basic_auth_private_file_low_timeout', 'apigee_edge_basic_auth', 'apigee_edge_private_file', $this->validCredentials);
    $this->createKey('key_oauth_private_file_low_timeout', 'apigee_edge_oauth', 'apigee_edge_private_file', $this->validCredentials);
    $timeout = 0.1;
    $this->setHttpClientParameters($timeout, $timeout);
    $this->assertKeyTestConnection('key_basic_auth_private_file_low_timeout', '', "Failed to connect to Apigee Edge. The connection timeout threshold ({$timeout}) or the request timeout ({$timeout}) is too low or something is wrong with the connection. Error message: cURL error 28:");
    $this->assertKeyTestConnection('key_oauth_private_file_low_timeout', 'key_oauth_token', "Failed to connect to the OAuth authorization server. The connection timeout threshold ({$timeout}) or the request timeout ({$timeout}) is too low or something is wrong with the connection. Error message: cURL error 28:");
    $this->assertKeySave('key_basic_auth_private_file_low_timeout', '', "Failed to connect to Apigee Edge. The connection timeout threshold ({$timeout}) or the request timeout ({$timeout}) is too low or something is wrong with the connection. Error message: cURL error 28:");
    $this->assertKeySave('key_oauth_private_file_low_timeout', 'key_oauth_token', "Failed to connect to the OAuth authorization server. The connection timeout threshold ({$timeout}) or the request timeout ({$timeout}) is too low or something is wrong with the connection. Error message: cURL error 28:");
    $this->setHttpClientParameters(30, 30);

    // Delete every key, check authentication form and state.
    foreach ($this->keys as $key_id) {
      Key::load($key_id)->delete();
    }
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));
    $this->assertEmpty($this->container->get('state')->get('apigee_edge.auth')['active_key']);
    $this->assertEmpty($this->container->get('state')->get('apigee_edge.auth')['active_key_oauth_token']);
    $this->assertSession()->pageTextContains(self::NO_AVAILABLE_BASIC_AUTH_KEY);
    $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_oauth');
    $this->assertSession()->pageTextContains(self::NO_AVAILABLE_OAUTH_KEY);
    $this->assertSession()->pageTextContains(self::NO_AVAILABLE_OAUTH_TOKEN_KEY);

    // Check Key form custom validations.
    $this->assertKeyFormValidation();

    // Only Apigee Edge keys can be used in SDK connector.
    $this->expectExceptionMessage('Type of default_key key does not implement EdgeKeyTypeInterface.');
    $this->container->get('apigee_edge.sdk_connector')->testConnection(Key::load('default_key'));
  }

  /**
   * Creates a new key.
   *
   * @param string $id
   *   The id of the key to create.
   * @param string $key_type
   *   The id of the key type.
   * @param string $key_provider
   *   The id of the key provider.
   * @param array $credentials
   *   API credentials to use.
   */
  protected function createKey(string $id, string $key_type, string $key_provider, array $credentials = []) {
    $web_assert = $this->assertSession();
    $this->drupalGet(Url::fromRoute('entity.key.add_form'));

    $this->getSession()->getPage()->fillField('label', $id);
    $this->getSession()->getPage()->selectFieldOption('key_type', $key_type);
    $web_assert->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('key_provider', $key_provider);
    $web_assert->assertWaitOnAjaxRequest();

    if (!empty($credentials)) {
      $this->getSession()->getPage()->fillField('key_input_settings[organization]', $credentials['organization']);
      $this->getSession()->getPage()->fillField('key_input_settings[username]', $credentials['username']);
      $this->getSession()->getPage()->fillField('key_input_settings[password]', $credentials['password']);
      if (isset($credentials['endpoint'])) {
        $this->getSession()->getPage()->fillField('key_input_settings[endpoint]', $credentials['endpoint']);
      }
      if (isset($credentials['authorization_server'])) {
        $this->getSession()->getPage()->fillField('key_input_settings[authorization_server]', $credentials['authorization_server']);
      }
      if (isset($credentials['client_id'])) {
        $this->getSession()->getPage()->fillField('key_input_settings[client_id]', $credentials['client_id']);
      }
      if (isset($credentials['client_secret'])) {
        $this->getSession()->getPage()->fillField('key_input_settings[client_secret]', $credentials['client_secret']);
      }
    }

    $this->getSession()->getPage()->pressButton('edit-submit');
    $this->assertSession()->pageTextContains("The key {$id} has been added.");
    $this->keys[] = $id;
  }

  /**
   * Checks connection test on the authentication form using the given keys.
   *
   * @param string $key_id
   *   Id of the key to test.
   * @param string $key_token_id
   *   Id of the token key to test.
   * @param string $message
   *   Displayed error message if the connection test should be failed.
   */
  protected function assertKeyTestConnection(string $key_id, string $key_token_id = '', string $message = '') {
    $web_assert = $this->assertSession();
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));

    if (empty($key_token_id)) {
      $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_basic_auth');
      $this->getSession()->getPage()->selectFieldOption('key_basic_auth', $key_id);
    }
    else {
      $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_oauth');
      $this->getSession()->getPage()->selectFieldOption('key_oauth', $key_id);
      $this->getSession()->getPage()->selectFieldOption('key_oauth_token', $key_token_id);
    }

    $this->getSession()->getPage()->pressButton('Send request');
    $web_assert->assertWaitOnAjaxRequest();
    if (empty($message)) {
      $this->assertSession()->pageTextContains('Connection successful');
      $this->assertSession()->pageTextNotContains(self::DEBUG_INFORMATION_TITLE);
    }
    else {
      $this->assertSession()->pageTextContains($message);
      $this->assertSession()->pageTextContains(self::DEBUG_INFORMATION_TITLE);
      $this->assertDebugText($key_id);
    }
  }

  /**
   * Checks save configuration on the authentication form using the given keys.
   *
   * @param string $key_id
   *   Id of the key to save.
   * @param string $key_token_id
   *   Id of the token key to save.
   * @param string $message
   *   Displayed error message if the configuration save should be failed.
   */
  protected function assertKeySave(string $key_id, string $key_token_id = '', string $message = '') {
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));

    if (empty($key_token_id)) {
      $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_basic_auth');
      $this->getSession()->getPage()->selectFieldOption('key_basic_auth', $key_id);
    }
    else {
      $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_oauth');
      $this->getSession()->getPage()->selectFieldOption('key_oauth', $key_id);
      $this->getSession()->getPage()->selectFieldOption('key_oauth_token', $key_token_id);
    }

    $this->getSession()->getPage()->pressButton('Save configuration');
    if (empty($message)) {
      $this->assertSession()->pageTextContains('The configuration options have been saved');
      $this->assertSession()->pageTextNotContains(self::DEBUG_INFORMATION_TITLE);
      $this->assertEquals($this->container->get('state')->get('apigee_edge.auth')['active_key'], $key_id);
      $this->assertEquals($this->container->get('state')->get('apigee_edge.auth')['active_key_oauth_token'], $key_token_id);
      $this->container->get('apigee_edge.sdk_connector')->testConnection();
    }
    else {
      $this->assertSession()->pageTextContains($message);
      $this->assertSession()->pageTextContains(self::DEBUG_INFORMATION_TITLE);
      $this->assertDebugText($key_id);
      $this->assertNotEquals($this->container->get('state')->get('apigee_edge.auth')['active_key'], $key_id);
    }
  }

  /**
   * Checks that the debug test does not contain sensitive information.
   *
   * @param string $key_id
   *   Id of the currently failed key.
   */
  protected function assertDebugText(string $key_id) {
    $web_assert = $this->assertSession();
    $debug_text = $web_assert->elementExists('css', '.form-textarea')->getValue();
    $key = Key::load($key_id);
    /** @var \Drupal\apigee_edge\Plugin\KeyType\BasicAuthKeyType $key_type */
    $key_type = $key->getKeyType();
    $this->assertRegExp('/.*Authorization: (Basic|Bearer) \*\*\*credentials\*\*\*.*/', $debug_text);
    $this->assertNotContains($key_type->getPassword($key), $debug_text);
    if ($key_type instanceof OauthKeyType && $key_type->getClientSecret($key) !== Oauth::DEFAULT_CLIENT_SECRET) {
      $this->assertNotContains($key_type->getClientSecret($key), $debug_text);
    }
  }

  /**
   * Checks custom validation of Key form.
   */
  protected function assertKeyFormValidation() {
    // Unset private file path.
    $settings['settings']['file_private_path'] = (object) [
      'value' => '',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
    $this->rebuildContainer();

    $web_assert = $this->assertSession();
    $this->drupalGet(Url::fromRoute('entity.key.add_form'));

    $this->getSession()->getPage()->fillField('label', $this->randomMachineName());
    $this->getSession()->getPage()->selectFieldOption('key_type', 'apigee_edge_basic_auth');
    $web_assert->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('key_provider', 'apigee_edge_private_file');
    $web_assert->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->fillField('key_input_settings[organization]', $this->validCredentials['organization']);
    $this->getSession()->getPage()->fillField('key_input_settings[username]', $this->validCredentials['username']);
    $this->getSession()->getPage()->fillField('key_input_settings[password]', $this->validCredentials['password']);

    $this->getSession()->getPage()->pressButton('edit-submit');
    $this->assertSession()->pageTextContains('The private file system is not configured properly. Visit the File system settings page to specify the private file system path.');
  }

  /**
   * Set HTTP client request and connect timeouts.
   *
   * @param float $connect_timeout
   *   The connect timeout.
   * @param float $request_timeout
   *   The request timeout.
   */
  protected function setHttpClientParameters(float $connect_timeout, float $request_timeout) {
    $this->drupalPostForm(Url::fromRoute('apigee_edge.settings.connection_config'), [
      'connect_timeout' => $connect_timeout,
      'request_timeout' => $request_timeout,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
  }

}
