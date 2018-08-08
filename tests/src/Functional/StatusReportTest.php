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
use Drupal\Core\Url;
use Drupal\key\Entity\Key;

/**
 * Status report test.
 *
 * @group apigee_edge
 */
class StatusReportTest extends ApigeeEdgeFunctionalTestBase {

  const KEY_NOT_FOUND = 'Apigee Edge API authentication key not found.';

  const KEY_MALFORMED = 'Apigee Edge API authentication key is malformed or not readable.';

  const CANNOT_CONNECT_SHORT = 'Cannot connect to Apigee Edge server.';

  const CANNOT_CONNECT_LONG = 'Cannot connect to Apigee Edge server. You have either given wrong credential details or the Apigee Edge server is unreachable. Visit the Apigee Edge Configuration page to get more information.';

  const CANNOT_CONNECT_MALFORMED = 'Cannot connect to Apigee Edge server. Check the settings and the requirements of the active key\'s provider. Visit the Key Configuration page to get more information.';

  /**
   * Tests invalid credentials.
   */
  public function testInvalidCredentials() {
    $this->drupalLogin($this->rootUser);
    $status_report_path = Url::fromRoute('system.status');

    // Ensure that pre-defined credentials are correctly set.
    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextNotContains(self::CANNOT_CONNECT_SHORT);

    // Delete authentication key.
    $this->invalidateKey();
    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextContains(self::KEY_NOT_FOUND);
    $this->assertSession()->pageTextContains(self::CANNOT_CONNECT_LONG);

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
    $this->setKey('private_file', '');

    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextNotContains(self::CANNOT_CONNECT_SHORT);

    // Use wrong credentials.
    $key->setKeyValue(Json::encode([
      'endpoint' => getenv('APIGEE_EDGE_ENDPOINT'),
      'organization' => getenv('APIGEE_EDGE_ORGANIZATION'),
      'username' => getenv('APIGEE_EDGE_USERNAME'),
      'password' => $this->getRandomGenerator()->string(),
    ]));
    $key->save();

    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextContains('Unauthorized');
    $this->assertSession()->pageTextContains(self::CANNOT_CONNECT_LONG);

    // Create new Apigee Edge OAuth key with private file provider.
    $key = Key::create([
      'id' => 'private_file_oauth',
      'label' => 'Private file oauth',
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

    // Create new Apigee Edge OAuth token key with private file provider.
    Key::create([
      'id' => 'private_file_token',
      'label' => 'Private file_token',
      'key_type' => 'apigee_edge_oauth_token',
      'key_provider' => 'apigee_edge_private_file',
      'key_input' => 'none',
    ])->save();
    $this->setKey('private_file_oauth', 'private_file_token');

    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextNotContains(self::CANNOT_CONNECT_SHORT);

    // Use wrong credentials.
    $key->setKeyValue(Json::encode([
      'endpoint' => getenv('APIGEE_EDGE_ENDPOINT'),
      'organization' => $this->getRandomGenerator()->name(),
      'username' => getenv('APIGEE_EDGE_USERNAME'),
      'password' => getenv('APIGEE_EDGE_PASSWORD'),
    ]));
    $key->save();

    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextContains('Forbidden');
    $this->assertSession()->pageTextContains(self::CANNOT_CONNECT_LONG);

    // Unset private file path.
    $settings['settings']['file_private_path'] = (object) [
      'value' => '',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
    $this->rebuildContainer();

    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextContains(self::KEY_MALFORMED);
    $this->assertSession()->pageTextContains(self::CANNOT_CONNECT_MALFORMED);
  }

}
