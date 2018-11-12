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
   */
  public function testAuthentication() {
    $active_key = Key::load($this->config(AuthenticationForm::CONFIG_NAME)->get('active_key'));
    $this->drupalGet(Url::fromRoute('apigee_edge.settings'));
    $this->assertSession()->fieldValueEquals('Organization', $active_key->getKeyType()->getOrganization($active_key));
    $this->assertSession()->fieldValueEquals('Username', $active_key->getKeyType()->getUsername($active_key));
  }

}
