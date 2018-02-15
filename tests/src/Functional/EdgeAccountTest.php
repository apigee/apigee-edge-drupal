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

use Drupal\apigee_edge\Entity\Developer;
use Drupal\Tests\BrowserTestBase;

/**
 * Edge account related tests.
 *
 * @group apigee_edge
 */
class EdgeAccountTest extends BrowserTestBase {

  /**
   * Credential storage.
   *
   * @var array
   */
  protected $credentials = [];

  public static $modules = [
    'apigee_edge',
  ];

  /**
   * Initializes the credentials property.
   *
   * @return bool
   *   True if the credentials are successfully initialized.
   */
  protected function initCredentials() : bool {
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
      $this->markTestSkipped('credentials not found');
    }
    parent::setUp();

    $this->drupalLogin($this->rootUser);
  }

  protected function resetCache() {
    \Drupal::entityTypeManager()->getStorage('developer')->resetCache();
  }

  /**
   * Tests environment credentials storage.
   */
  public function testCredentialsStorages() {
    // Test private file storage.
    $this->drupalGet('/admin/config/apigee-edge/settings');

    $formdata = [
      'credentials_storage_type' => 'credentials_storage_private_file',
      'credentials_api_organization' => $this->credentials['organization'],
      'credentials_api_endpoint' => $this->credentials['endpoint'],
      'credentials_api_username' => $this->credentials['username'],
      'credentials_api_password' => $this->credentials['password'],
    ];

    $this->submitForm($formdata, t('Send request'));
    $this->assertSession()->pageTextContains(t('Connection successful'));

    $this->submitForm($formdata, t('Save configuration'));
    $this->assertSession()->pageTextContains(t('The configuration options have been saved'));

    $developer_data = [
      'userName' => $this->randomMachineName(),
      'firstName' => $this->getRandomGenerator()->word(16),
      'lastName' => $this->getRandomGenerator()->word(16),
    ];
    $developer_data['email'] = "{$developer_data['userName']}@example.com";

    $developer = Developer::create($developer_data);
    $developer->save();

    $this->resetCache();

    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = Developer::load($developer_data['email']);
    $this->assertEquals($developer->getEmail(), $developer_data['email']);

    // Test env storage.
    $this->drupalGet('/admin/config/apigee-edge/settings');

    $formdata = [
      'credentials_storage_type' => 'credentials_storage_env',
    ];

    $this->submitForm($formdata, t('Send request'));
    $this->assertSession()->pageTextContains(t('Connection successful'));

    $this->submitForm($formdata, t('Save configuration'));
    $this->assertSession()->pageTextContains(t('The configuration options have been saved'));

    $this->resetCache();

    $developer = Developer::load($developer_data['email']);
    $this->assertEquals($developer->getEmail(), $developer_data['email']);

    $developer->delete();
  }

}
