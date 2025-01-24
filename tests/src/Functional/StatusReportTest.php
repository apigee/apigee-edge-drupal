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
use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\key\Entity\Key;

/**
 * Status report test.
 *
 * @group apigee_edge
 */
class StatusReportTest extends ApigeeEdgeFunctionalTestBase {

  const KEY_NOT_SET = 'Apigee Edge API authentication key is not set.';

  const KEY_NOT_FOUND = 'Apigee Edge API authentication key not found with "default" id.';

  const KEY_MALFORMED = 'Apigee Edge API authentication key is malformed or not readable.';

  const CANNOT_CONNECT_SHORT = 'Cannot connect to Apigee Edge server.';

  const CANNOT_CONNECT_LONG = 'Cannot connect to Apigee server. You have either given wrong credential details or the Apigee server is unreachable. Visit the Apigee general settings page to get more information.';

  /**
   * {@inheritdoc}
   */
  protected static $mock_api_client_ready = TRUE;

  /**
   * Tests invalid credentials.
   */
  public function testInvalidCredentials() {
    $orgName = $this->sdkConnector->getOrganization();

    $this->drupalLogin($this->rootUser);
    $status_report_path = Url::fromRoute('system.status');

    // Ensure that pre-defined credentials are correctly set.
    $this->stack->queueMockResponse(['org' => ['org_name' => $orgName]]);
    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextNotContains(self::CANNOT_CONNECT_SHORT);

    // Delete authentication key.
    $this->invalidateKey();
    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextContains(self::KEY_NOT_SET);
    $this->assertSession()->pageTextContains(self::CANNOT_CONNECT_LONG);

    // Set invalid authentication key id.
    $this->setKey('default');
    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextContains(self::KEY_NOT_FOUND);
    $this->assertSession()->pageTextContains(self::CANNOT_CONNECT_LONG);

    // Create new Apigee Edge basic auth key with private file provider.
    $key = Key::create([
      'id' => 'private_file',
      'label' => 'Private file',
      'key_type' => 'apigee_auth',
      'key_provider' => 'apigee_edge_private_file',
      'key_input' => 'apigee_auth_input',
    ]);
    $key->setKeyValue(Json::encode([
      'auth_type' => getenv('APIGEE_EDGE_AUTH_TYPE'),
      'endpoint' => getenv('APIGEE_EDGE_ENDPOINT'),
      'organization' => getenv('APIGEE_EDGE_ORGANIZATION'),
      'username' => getenv('APIGEE_EDGE_USERNAME'),
      'password' => getenv('APIGEE_EDGE_PASSWORD'),
    ]));
    $key->save();
    $this->setKey('private_file');

    $this->stack->queueMockResponse(['org' => ['org_name' => $orgName]]);
    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextNotContains(self::CANNOT_CONNECT_SHORT);

    // Use wrong credentials.
    $key->setKeyValue(Json::encode([
      'auth_type' => getenv('APIGEE_EDGE_AUTH_TYPE'),
      'endpoint' => getenv('APIGEE_EDGE_ENDPOINT'),
      'organization' => getenv('APIGEE_EDGE_ORGANIZATION'),
      'username' => getenv('APIGEE_EDGE_USERNAME'),
      'password' => $this->getRandomGenerator()->string(),
    ]));
    $key->save();

    $this->stack->queueMockResponse(['get_not_found' => ['status_code' => 401]]);
    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextContains(self::CANNOT_CONNECT_LONG);

    // Create new Apigee Edge OAuth key with private file provider.
    $key = Key::create([
      'id' => 'private_file_oauth',
      'label' => 'Private file oauth',
      'key_type' => 'apigee_auth',
      'key_provider' => 'apigee_edge_private_file',
      'key_input' => 'apigee_auth_input',
    ]);
    $key->setKeyValue(Json::encode([
      'auth_type' => EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH,
      'endpoint' => getenv('APIGEE_EDGE_ENDPOINT'),
      'organization' => getenv('APIGEE_EDGE_ORGANIZATION'),
      'username' => getenv('APIGEE_EDGE_USERNAME'),
      'password' => getenv('APIGEE_EDGE_PASSWORD'),
    ]));
    $key->save();

    $this->setKey('private_file_oauth');

    $this->stack->queueMockResponse('access_token');
    $this->stack->queueMockResponse(['org' => ['org_name' => $orgName]]);
    $this->drupalGet($status_report_path);
    $this->assertSession()->pageTextNotContains(self::CANNOT_CONNECT_SHORT);

    // Use wrong credentials.
    $key->setKeyValue(Json::encode([
      'auth_type' => getenv('APIGEE_EDGE_AUTH_TYPE'),
      'endpoint' => getenv('APIGEE_EDGE_ENDPOINT'),
      'organization' => $this->getRandomGenerator()->name(),
      'username' => getenv('APIGEE_EDGE_USERNAME'),
      'password' => getenv('APIGEE_EDGE_PASSWORD'),
    ]));
    $key->save();

    $this->stack->queueMockResponse('get_not_found');
    $this->drupalGet($status_report_path);
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
    $this->assertSession()->pageTextContains(self::CANNOT_CONNECT_LONG);
  }

}
