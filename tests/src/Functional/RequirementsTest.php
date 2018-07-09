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
 * Status report test.
 *
 * @group apigee_edge
 */
class RequirementsTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests invalid credentials.
   */
  public function testInvalidCredentials() {
    // Ensure that pre-defined credentials are correctly set.
    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->pageTextNotContains('Cannot connect to Apigee Edge server.');

    // Delete authentication key.
    $this->invalidateKey();
    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->pageTextContains('Apigee Edge API authentication key not found.');
    $this->assertSession()->pageTextContains('Cannot connect to Apigee Edge server. You have either given wrong credential details or the Edge server is unreachable. Visit the Apigee Edge Configuration page to get more information.');

    // Create new Apigee Edge basic auth key with private file provider.
    $key = Key::create([
      'id' => 'private_file',
      'label' => 'Private file',
      'key_type' => 'apigee_edge_basic_auth',
      'key_provider' => 'apigee_edge_private_file',
      'key_input' => 'apigee_edge_basic_auth_input',
    ]);
    $key->setKeyValue(Json::encode([
      'endpoint' => getenv('APIGEE_EDGE_ENDPOINT'),
      'organization' => getenv('APIGEE_EDGE_ORGANIZATION'),
      'username' => getenv('APIGEE_EDGE_USERNAME'),
      'password' => getenv('APIGEE_EDGE_PASSWORD'),
    ]));
    $key->save();
    $this->container->get('state')->set('apigee_edge.client.active_key', 'private_file');

    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->pageTextNotContains('Cannot connect to Apigee Edge server.');
    $this->container->get('apigee_edge.sdk_connector')->testConnection();

    // Use wrong credentials.
    $key->setKeyValue(Json::encode([
      'endpoint' => getenv('APIGEE_EDGE_ENDPOINT'),
      'organization' => getenv('APIGEE_EDGE_ORGANIZATION'),
      'username' => getenv('APIGEE_EDGE_USERNAME'),
      'password' => $this->getRandomGenerator()->string(),
    ]));
    $key->save();

    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->pageTextContains('Unauthorized');
    $this->assertSession()->pageTextContains('Cannot connect to Apigee Edge server. You have either given wrong credential details or the Edge server is unreachable. Visit the Apigee Edge Configuration page to get more information.');

    // Delete authentication key.
    Key::load('private_file')->delete();

    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->pageTextContains('Apigee Edge API authentication key not found.');
    $this->assertSession()->pageTextContains('Cannot connect to Apigee Edge server. You have either given wrong credential details or the Edge server is unreachable. Visit the Apigee Edge Configuration page to get more information.');

    // Create new Apigee Edge OAuth key with private file provider.
    $key = Key::create([
      'id' => 'private_file',
      'label' => 'Private file',
      'key_type' => 'apigee_edge_oauth',
      'key_provider' => 'apigee_edge_private_file',
      'key_input' => 'apigee_edge_oauth_input',
    ]);
    $key->setKeyValue(Json::encode([
      'endpoint' => getenv('APIGEE_EDGE_ENDPOINT'),
      'organization' => getenv('APIGEE_EDGE_ORGANIZATION'),
      'username' => getenv('APIGEE_EDGE_USERNAME'),
      'password' => getenv('APIGEE_EDGE_PASSWORD'),
    ]));
    $key->save();
    $this->container->get('state')->set('apigee_edge.client.active_key', 'private_file');

    // Create new Apigee Edge OAuth token key with private file provider.
    Key::create([
      'id' => 'private_file_token',
      'label' => 'Private file_token',
      'key_type' => 'apigee_edge_oauth_token',
      'key_provider' => 'apigee_edge_private_file',
      'key_input' => 'none',
    ])->save();
    $this->container->get('state')->set('apigee_edge.client.active_key_oauth_token', 'private_file_token');

    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->pageTextNotContains('Cannot connect to Apigee Edge server.');
    $this->container->get('apigee_edge.sdk_connector')->testConnection();

    // Use wrong credentials.
    $key->setKeyValue(Json::encode([
      'endpoint' => getenv('APIGEE_EDGE_ENDPOINT'),
      'organization' => $this->getRandomGenerator()->name(),
      'username' => getenv('APIGEE_EDGE_USERNAME'),
      'password' => getenv('APIGEE_EDGE_PASSWORD'),
    ]));
    $key->save();

    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->pageTextContains('Forbidden');
    $this->assertSession()->pageTextContains('Cannot connect to Apigee Edge server. You have either given wrong credential details or the Edge server is unreachable. Visit the Apigee Edge Configuration page to get more information.');

    // Unset private file path.
    $settings['settings']['file_private_path'] = (object) [
      'value' => '',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
    $this->rebuildContainer();

    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->pageTextContains('Apigee Edge API authentication key is malformed or not readable.');
    $this->assertSession()->pageTextContains('Cannot connect to Apigee Edge server. Check the settings and the requirements of the active key\'s provider. Visit the Key Configuration page to get more information.');
  }

}
