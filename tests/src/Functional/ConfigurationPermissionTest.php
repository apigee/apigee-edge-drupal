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

use Drupal\Core\Discovery\YamlDiscovery;
use Drupal\Core\Url;

/**
 * Module administration permission test.
 *
 * @group apigee_edge
 * @group apigee_edge_permissions
 */
class ConfigurationPermissionTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalPlaceBlock('local_actions_block');
  }

  /**
   * Tests access to the admin pages with admin/authenticated/anonymous roles.
   */
  public function testAccess() {
    // Test access with admin role.
    $this->drupalLogin($this->rootUser);
    $this->assertPaths(TRUE);

    // Test access with authenticated role. It is not necessary to create a
    // developer here so skip apigee_edge_user_presave().
    $this->disableUserPresave();
    $account = $this->createAccount();
    $this->enableUserPresave();
    $this->drupalLogin($account);
    $this->assertPaths(FALSE);

    // Test access with anonymous role.
    $this->drupalLogout();
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

    $visit_path = function (string $path, array $query = []) use ($expected_code) {
      $options = [];
      if ($query) {
        $options['query'] = $query;
      }
      $this->drupalGetNoMetaRefresh($path, $options);
      $this->assertEquals($expected_code, $this->getSession()->getStatusCode(), $path);
    };

    // Get all routes defined by the module and check every route that requires
    // the permission "administer apigee edge".
    $module_path = $this->container->get('module_handler')->getModule('apigee_edge')->getPath();
    $discovery = new YamlDiscovery('routing', [
      'apigee_edge' => DRUPAL_ROOT . '/' . $module_path,
    ]);
    $module_routes = $discovery->findAll()['apigee_edge'];

    // These routes are checked manually.
    unset($module_routes['apigee_edge.developer_sync.run'], $module_routes['apigee_edge.developer_sync.schedule']);

    foreach ($module_routes as $route => $data) {
      // Check routes that require permission "administer apigee edge".
      if (in_array('administer apigee edge', $data['requirements'])) {
        $visit_path($data['path']);
        if ($route === 'apigee_edge.settings.developer.sync') {
          if ($access) {
            list($schedule_path, $schedule_query) = $this->findLink('Background developer sync');
            list($run_path, $run_query) = $this->findLink('Run developer sync');
            $visit_path($schedule_path, $schedule_query);
            $visit_path($run_path, $run_query);
          }
          else {
            $visit_path(Url::fromRoute('apigee_edge.developer_sync.run')->getInternalPath());
            $visit_path(Url::fromRoute('apigee_edge.developer_sync.schedule')->getInternalPath());
          }
        }
      }
    }
  }

}
