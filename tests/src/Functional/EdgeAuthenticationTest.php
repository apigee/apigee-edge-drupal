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

use Drupal\Component\Serialization\Json;
use Drupal\key\Entity\Key;

/**
 * Apigee Edge API credentials, authentication form, key integration test.
 *
 * @group apigee_edge
 */
class EdgeAuthenticationTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * Credential storage.
   *
   * @var array
   */
  protected $credentials = [];

  /**
   * Initializes the credentials property.
   *
   * @return bool
   *   True if the credentials are successfully initialized.
   */
  protected function initCredentials(): bool {
    if (($username = getenv('APIGEE_EDGE_USERNAME'))) {
      $this->credentials['username'] = $username;
    }
    if (($password = getenv('APIGEE_EDGE_PASSWORD'))) {
      $this->credentials['password'] = $password;
    }
    if (($organization = getenv('APIGEE_EDGE_ORGANIZATION'))) {
      $this->credentials['organization'] = $organization;
    }
    if (($endpoint = getenv('APIGEE_EDGE_ENDPOINT'))) {
      $this->credentials['endpoint'] = $endpoint;
    }

    return (bool) $this->credentials;
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
  public function testEdgeAuthentication() {
    // Delete pre-defined test key.
    $this->drupalPostForm('/admin/config/system/keys/manage/test/delete', [], 'Delete');
    $this->assertSession()->pageTextContains('The key test has been deleted.');
    $this->assertEmpty($this->container->get('state')->get('apigee_edge.auth')['active_key']);
    $this->assertEmpty($this->container->get('state')->get('apigee_edge.auth')['active_key_oauth_token']);

    // Create a key entity using key's default type and provider.
    $default_key = Key::create([
      'id' => 'default_key',
    ]);
    $default_key->save();

    // Visit authentication form and ensure that there
    // are no available Apigee Edge keys.
    $this->drupalGet('/admin/config/apigee-edge/settings');
    $this->assertSession()->pageTextContains('There is no available basic authentication key for connecting to Apigee Edge.');
    $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_oauth');
    $this->assertSession()->pageTextContains('There is no available OAuth key for connecting to Apigee Edge.');
    $this->assertSession()->pageTextContains('There is no available OAuth token key for connecting to Apigee Edge.');

    // Add new Apigee Edge basic authentication key
    // with environment variables provider.
    $key = Key::create([
      'id' => 'key_test_env_variables',
      'label' => 'Test key in environment variables',
      'key_type' => 'apigee_edge_basic_auth',
      'key_provider' => 'apigee_edge_environment_variables',
      'key_input' => 'apigee_edge_basic_auth_input',
    ]);
    $key->save();

    // Add new Apigee Edge basic authentication key
    // with private file provider.
    $key = Key::create([
      'id' => 'key_test_private_file',
      'label' => 'Test key in private file',
      'key_type' => 'apigee_edge_basic_auth',
      'key_provider' => 'apigee_edge_private_file',
      'key_input' => 'apigee_edge_basic_auth_input',
    ]);
    $key->setKeyValue(Json::encode($this->credentials));
    $key->save();

    // Add new, wrong Apigee Edge basic authentication key
    // with private file provider.
    $key = Key::create([
      'id' => 'key_wrong_test_private_file',
      'label' => 'Wrong test key in private file',
      'key_type' => 'apigee_edge_basic_auth',
      'key_provider' => 'apigee_edge_private_file',
      'key_input' => 'apigee_edge_basic_auth_input',
    ]);
    $key->setKeyValue(Json::encode([
      'endpoint' => 'malformed',
      'organization' => 'malformed',
      'username' => 'malformed@example.com',
      'password' => 'malformed',
    ]));
    $key->save();

    // Visit authentication form and check API connection with the stored keys.
    $this->drupalGet('/admin/config/apigee-edge/settings');
    $this->assertSession()->pageTextContains('Test key in environment variables');
    $this->assertSession()->pageTextContains('Test key in private file');
    $this->assertSession()->pageTextContains('Wrong test key in private file');
    $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_oauth');
    $this->assertSession()->pageTextContains('There is no available OAuth key for connecting to Apigee Edge.');
    $this->assertSession()->pageTextContains('There is no available OAuth token key for connecting to Apigee Edge.');

    // Test key stored in environment variables.
    $this->drupalPostForm('/admin/config/apigee-edge/settings', [
      'key_basic_auth' => 'key_test_env_variables',
    ], 'Send request');
    $this->assertSession()->pageTextContains('Connection successful');

    $this->drupalPostForm('/admin/config/apigee-edge/settings', [
      'key_basic_auth' => 'key_test_env_variables',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved');

    $this->assertEquals($this->container->get('state')->get('apigee_edge.auth')['active_key'], 'key_test_env_variables');
    $this->assertEmpty($this->container->get('state')->get('apigee_edge.auth')['active_key_oauth_token']);
    $this->container->get('apigee_edge.sdk_connector')->testConnection();

    // Test key stored in private file.
    $this->drupalPostForm('/admin/config/apigee-edge/settings', [
      'key_basic_auth' => 'key_test_private_file',
    ], 'Send request');
    $this->assertSession()->pageTextContains('Connection successful');

    $this->drupalPostForm('/admin/config/apigee-edge/settings', [
      'key_basic_auth' => 'key_test_private_file',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved');

    $this->assertEquals($this->container->get('state')->get('apigee_edge.auth')['active_key'], 'key_test_private_file');
    $this->assertEmpty($this->container->get('state')->get('apigee_edge.auth')['active_key_oauth_token']);
    $this->container->get('apigee_edge.sdk_connector')->testConnection();

    // Test wrong key stored in private file.
    $this->drupalPostForm('/admin/config/apigee-edge/settings', [
      'key_basic_auth' => 'key_wrong_test_private_file',
    ], 'Send request');
    $this->assertSession()->pageTextContains('Connection failed.');

    $this->drupalPostForm('/admin/config/apigee-edge/settings', [
      'key_basic_auth' => 'key_wrong_test_private_file',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('Connection failed.');

    $this->assertEquals($this->container->get('state')->get('apigee_edge.auth')['active_key'], 'key_test_private_file');
    $this->assertEmpty($this->container->get('state')->get('apigee_edge.auth')['active_key_oauth_token']);
    $this->container->get('apigee_edge.sdk_connector')->testConnection();

    // Add new Apigee Edge OAuth token key
    // with private file provider.
    $key = Key::create([
      'id' => 'key_test_oauth_token',
      'label' => 'Test OAuth token key in private file',
      'key_type' => 'apigee_edge_oauth_token',
      'key_provider' => 'apigee_edge_private_file',
      'key_input' => 'none',
    ]);
    $key->save();

    // Add new Apigee Edge OAuth key
    // with environment variables provider.
    $key = Key::create([
      'id' => 'key_test_oauth_env_variables',
      'label' => 'Test OAuth key in environment variables',
      'key_type' => 'apigee_edge_oauth',
      'key_provider' => 'apigee_edge_environment_variables',
      'key_input' => 'apigee_edge_oauth_input',
    ]);
    $key->save();

    // Add new Apigee Edge OAuth key
    // with private file provider.
    $key = Key::create([
      'id' => 'key_test_oauth_private_file',
      'label' => 'Test OAuth key in private file',
      'key_type' => 'apigee_edge_oauth',
      'key_provider' => 'apigee_edge_private_file',
      'key_input' => 'apigee_edge_oauth_input',
    ]);
    $key->setKeyValue(Json::encode($this->credentials));
    $key->save();

    // Add new, wrong Apigee Edge OAuth key
    // with private file provider.
    $key = Key::create([
      'id' => 'key_wrong_test_oauth_private_file',
      'label' => 'Wrong test OAuth key in private file',
      'key_type' => 'apigee_edge_oauth',
      'key_provider' => 'apigee_edge_private_file',
      'key_input' => 'apigee_edge_oauth_input',
    ]);
    $key->setKeyValue(Json::encode([
      'endpoint' => 'malformed',
      'organization' => 'malformed',
      'username' => 'malformed@example.com',
      'password' => 'malformed',
    ]));
    $key->save();

    // Visit authentication form and check API connection with the stored keys.
    $this->drupalGet('/admin/config/apigee-edge/settings');
    $this->assertSession()->pageTextContains('Test key in environment variables');
    $this->assertSession()->pageTextContains('Test key in private file');
    $this->assertSession()->pageTextContains('Wrong test key in private file');
    $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_oauth');
    $this->assertSession()->pageTextContains('Test OAuth token key in private file');
    $this->assertSession()->pageTextContains('Test OAuth key in environment variables');
    $this->assertSession()->pageTextContains('Test OAuth key in private file');
    $this->assertSession()->pageTextContains('Wrong test Oauth key in private file');

    // Test key stored in environment variables.
    $this->drupalPostForm('/admin/config/apigee-edge/settings', [
      'key_type' => 'apigee_edge_oauth',
      'key_oauth' => 'key_test_oauth_env_variables',
      'key_oauth_token' => 'key_test_oauth_token',
    ], 'Send request');
    $this->assertSession()->pageTextContains('Connection successful');

    $this->drupalPostForm('/admin/config/apigee-edge/settings', [
      'key_type' => 'apigee_edge_oauth',
      'key_oauth' => 'key_test_oauth_env_variables',
      'key_oauth_token' => 'key_test_oauth_token',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved');

    $this->assertEquals($this->container->get('state')->get('apigee_edge.auth')['active_key'], 'key_test_oauth_env_variables');
    $this->assertEquals($this->container->get('state')->get('apigee_edge.auth')['active_key_oauth_token'], 'key_test_oauth_token');
    $this->container->get('apigee_edge.sdk_connector')->testConnection();

    // Test key stored in private file.
    $this->drupalPostForm('/admin/config/apigee-edge/settings', [
      'key_type' => 'apigee_edge_oauth',
      'key_oauth' => 'key_test_oauth_private_file',
      'key_oauth_token' => 'key_test_oauth_token',
    ], 'Send request');
    $this->assertSession()->pageTextContains('Connection successful');

    $this->drupalPostForm('/admin/config/apigee-edge/settings', [
      'key_type' => 'apigee_edge_oauth',
      'key_oauth' => 'key_test_oauth_private_file',
      'key_oauth_token' => 'key_test_oauth_token',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved');

    $this->assertEquals($this->container->get('state')->get('apigee_edge.auth')['active_key'], 'key_test_oauth_private_file');
    $this->assertEquals($this->container->get('state')->get('apigee_edge.auth')['active_key_oauth_token'], 'key_test_oauth_token');
    $this->container->get('apigee_edge.sdk_connector')->testConnection();

    // Test wrong key stored in private file.
    $this->drupalPostForm('/admin/config/apigee-edge/settings', [
      'key_type' => 'apigee_edge_oauth',
      'key_oauth' => 'key_wrong_test_oauth_private_file',
      'key_oauth_token' => 'key_test_oauth_token',
    ], 'Send request');
    $this->assertSession()->pageTextContains('Connection failed.');

    $this->drupalPostForm('/admin/config/apigee-edge/settings', [
      'key_type' => 'apigee_edge_oauth',
      'key_oauth' => 'key_wrong_test_oauth_private_file',
      'key_oauth_token' => 'key_test_oauth_token',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('Connection failed.');

    $this->assertEquals($this->container->get('state')->get('apigee_edge.auth')['active_key'], 'key_test_oauth_private_file');
    $this->assertEquals($this->container->get('state')->get('apigee_edge.auth')['active_key_oauth_token'], 'key_test_oauth_token');
    $this->container->get('apigee_edge.sdk_connector')->testConnection();

    Key::load('key_test_env_variables')->delete();
    Key::load('key_test_private_file')->delete();
    Key::load('key_wrong_test_private_file')->delete();
    Key::load('key_test_oauth_token')->delete();
    Key::load('key_test_oauth_env_variables')->delete();
    Key::load('key_test_oauth_private_file')->delete();
    Key::load('key_wrong_test_oauth_private_file')->delete();
    $this->drupalGet('/admin/config/apigee-edge/settings');
    $this->assertEmpty($this->container->get('state')->get('apigee_edge.auth')['active_key']);
    $this->assertEmpty($this->container->get('state')->get('apigee_edge.auth')['active_key_oauth_token']);
    $this->assertSession()->pageTextContains('There is no available basic authentication key for connecting to Apigee Edge.');
    $this->getSession()->getPage()->selectFieldOption('edit-key-type', 'apigee_edge_oauth');
    $this->assertSession()->pageTextContains('There is no available OAuth key for connecting to Apigee Edge.');
    $this->assertSession()->pageTextContains('There is no available OAuth token key for connecting to Apigee Edge.');

    // Only Apigee Edge keys are usable in SDK connector.
    $this->expectExceptionMessage('Type of default_key key does not implement EdgeKeyTypeInterface.');
    $this->container->get('apigee_edge.sdk_connector')->testConnection($default_key);
  }

}
