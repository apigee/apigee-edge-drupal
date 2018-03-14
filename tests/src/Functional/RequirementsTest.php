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
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testInvalidCredentials() {
    // Ensure that pre-defined credentials are correctly set.
    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->pageTextNotContains('Cannot connect to Edge server.');

    // Delete authentication key.
    Key::load('test')->delete();

    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->pageTextContains('Apigee Edge API authentication key is not set.');
    $this->assertSession()->pageTextContains('Cannot connect to Apigee Edge server. You have either given wrong credential details or the Edge server is unreachable. Visit the Apigee Edge Configuration page to get more information.');

    // Create new Apigee Edge key with private file provider.
    $key = Key::create([
      'id' => 'private_file',
      'label' => 'Private file',
      'key_type' => 'apigee_edge_basic_auth',
      'key_provider' => 'apigee_edge_basic_auth_private_file',
      'key_input' => 'apigee_edge_basic_auth_input',
    ]);
    $key->setKeyValue(Json::encode([
      'endpoint' => getenv('APIGEE_EDGE_ENDPOINT'),
      'organization' => getenv('APIGEE_EDGE_ORGANIZATION'),
      'username' => getenv('APIGEE_EDGE_USERNAME'),
      'password' => getenv('APIGEE_EDGE_PASSWORD'),
    ]));
    $key->save();
    $this->config('apigee_edge.authentication')->set('active_key', 'private_file')->save();

    $this->assertSession()->pageTextNotContains('Cannot connect to Edge server.');

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
