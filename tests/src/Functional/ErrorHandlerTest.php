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
 * Apigee Edge API connection error page tests.
 *
 * @group apigee_edge
 */
class ErrorHandlerTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * Tests connection error page configuration.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testErrorPage() {
    $this->drupalLogin($this->rootUser);
    $errorPageTitle = $this->getRandomGenerator()->word(16);
    $this->drupalPostForm('/admin/config/apigee-edge/error-page-settings', [
      'error_page_title' => $errorPageTitle,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $paths = [
      '/exception/entity-storage',
      '/exception/api',
    ];

    foreach ($paths as $path) {
      $this->drupalGet($path);
      $this->assertSession()->pageTextContains($errorPageTitle);
    }
  }

}
