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

/**
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
    \Drupal::configFactory()->getEditable('apigee_edge.credentials_storage')
      ->set('credentials_storage_type', 'credentials_storage_private_file')
      ->save();

    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->pageTextContains('Cannot connect to Edge server. You have either given wrong credential details or the Edge server is unreachable.');
  }

  /**
   * Tests invalid private file storage.
   */
  public function testInvalidPrivateFileStorage() {
    \Drupal::configFactory()->getEditable('apigee_edge.credentials_storage')
      ->set('credentials_storage_type', 'credentials_storage_private_file')
      ->save();

    // Taken from Drupal\Tests\system\Functional\File\ConfigTest::testFileConfigurationPage().
    $settings['settings']['file_private_path'] = (object) [
      'value' => '',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
    $this->rebuildContainer();

    $this->drupalGet('/admin/reports/status');
    $this->assertSession()->pageTextContains('Cannot connect to Edge server, because your private file system is not configured properly.');
  }

}
