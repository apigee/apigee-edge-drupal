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
class ConfigurationPermissionTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'apigee_edge',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalPlaceBlock('local_actions_block');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    if ($this->loggedInUser) {
      $account = $this->loggedInUser;
      $this->drupalLogout();
      $account->delete();
    }
    parent::tearDown();
  }

  /**
   * Tests access to the admin pages with an admin account.
   */
  public function testAdminAccess() {
    $account = $this->createAccount(['administer apigee edge']);
    $this->drupalLogin($account);
    $this->assertPaths(TRUE);
  }

  /**
   * Tests access to the admin pages with a normal account.
   */
  public function testAuthenticatedAccess() {
    $account = $this->createAccount([]);
    $this->drupalLogin($account);
    $this->assertPaths(FALSE);
  }

  /**
   * Tests access to the admin pages as an anonoymous user.
   */
  public function testAnonymousAccess() {
    if ($this->loggedInUser) {
      $this->drupalLogout();
    }
    $this->assertPaths(FALSE);
  }

  /**
   * Checks access to the admin pages.
   *
   * @param bool $access
   *   Whether the current user should access the pages or not.
   */
  protected function assertPaths(bool $access) {
    $expected_code = $access ? 200 : 403;

    $visit_path = function(string $path, array $query = []) use($expected_code) {
      $options = [];
      if ($query) {
        $options['query'] = $query;
      }
      $this->drupalGetNoMetaRefresh($path, $options);
      $this->assertEquals($expected_code, $this->getSession()->getStatusCode(), $path);
    };

    $visit_path('/admin/config/apigee-edge');
    $visit_path('/admin/config/apigee-edge/settings');
    if ($access) {
      list($schedule_path, $schedule_query) = $this->findLink('Background...');
      list($run_path, $run_query) = $this->findLink('Now');
      $visit_path($schedule_path, $schedule_query);
      $visit_path($run_path, $run_query);
    }
    else {
      $visit_path('/admin/config/apigee-edge/sync/schedule');
      $visit_path('/admin/config/apigee-edge/sync/run');
    }

    $visit_path('/admin/config/apigee-edge/product-settings');
    $visit_path('/admin/config/apigee-edge/app-settings');
    $visit_path('/admin/config/apigee-edge/error-page-settings');
  }

}
