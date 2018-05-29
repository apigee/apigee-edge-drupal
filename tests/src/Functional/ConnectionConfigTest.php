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
 * HTTP client, connection config test.
 *
 * @group apigee_edge
 */
class ConnectionConfigTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * Tests connection config form, HTTP client configuration.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \ReflectionException
   * @throws \Exception
   */
  public function testHttpClientConfig() {
    $this->drupalLogin($this->rootUser);

    $connect_timeout = random_int(300, 1000) / 10;
    $request_timeout = random_int(300, 1000) / 10;

    $this->drupalPostForm('/admin/config/apigee-edge/connection-config', [
      'connect_timeout' => $connect_timeout,
      'request_timeout' => $request_timeout,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved');

    $sdk_connector = $this->container->get('apigee_edge.sdk_connector');
    $sdk_connector->testConnection();

    // Update the test process's kernel with a new service container.
    $this->rebuildContainer();
    $sdk_connector = $this->container->get('apigee_edge.sdk_connector');
    // Get the client object from the SDK connector.
    $http_client = parent::getInvisibleProperty($sdk_connector, 'httpClient')->getValue($sdk_connector);
    $client = parent::getInvisibleProperty($http_client, 'client')->getValue($http_client);

    $this->assertEquals($connect_timeout, $client->getConfig('connect_timeout'));
    $this->assertEquals($request_timeout, $client->getConfig('timeout'));
  }

}
