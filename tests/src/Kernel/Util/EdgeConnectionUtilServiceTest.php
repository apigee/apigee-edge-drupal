<?php

/**
 * Copyright 2019 Google Inc.
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

namespace Drupal\Tests\apigee_edge\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * EdgeConnectionUtilService Edge tests.
 *
 * Make sure Edge API works as expected for the EdgeConnectionUtilService.
 *
 * These tests validate Edge API request/responses needed for
 * EdgeConnectionUtilService are valid.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class EdgeConnectionUtilServiceTest extends KernelTestBase implements ServiceModifierInterface {

  protected const TEST_ROLE_NAME = 'temp_role';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  protected $endpoint;
  protected $organization;
  protected $orgadmin_email;
  protected $orgadmin_password;

  protected $http_client;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $environment_vars = [
      'APIGEE_EDGE_ENDPOINT',
      'APIGEE_EDGE_ORGANIZATION',
      'APIGEE_EDGE_USERNAME',
      'APIGEE_EDGE_PASSWORD',
    ];

    foreach ($environment_vars as $environment_var) {
      if (!getenv($environment_var)) {
        $this->markTestSkipped('Environment variable ' . $environment_var . ' is not set, cannot run tests. See CONTRIBUTING.md for more information.');
      }
    }

    // Get environment variables for Edge connection.
    $this->endpoint = getenv('APIGEE_EDGE_ENDPOINT');
    $this->organization = getenv('APIGEE_EDGE_ORGANIZATION');
    $this->orgadmin_email = getenv('APIGEE_EDGE_USERNAME');
    $this->orgadmin_password = getenv('APIGEE_EDGE_PASSWORD');

    /** @var \GuzzleHttp\Client $client */
    $this->http_client = $this->container->get('http_client');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $url = $this->endpoint . '/o/' . $this->organization . '/userroles/' . self::TEST_ROLE_NAME;
    $response = $this->http_client->get($url, [
      'http_errors' => FALSE,
      'auth' => [$this->orgadmin_email, $this->orgadmin_password],
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);

    if ($response->getStatusCode() == 200) {
      $url = $this->endpoint . '/o/' . $this->organization . '/userroles/' . self::TEST_ROLE_NAME;
      $this->http_client->delete($url, [
        'auth' => [$this->orgadmin_email, $this->orgadmin_password],
        'headers' => [
          'Accept' => 'application/json',
          'Content-Type' => 'application/json',
        ],
      ]);
    }
    parent::tearDown();

  }

  /**
   * Fix for outbound HTTP requests fail with KernelTestBase.
   *
   * See comment #10:
   * https://www.drupal.org/project/drupal/issues/2571475#comment-11938008
   */
  public function alter(ContainerBuilder $container) {
    $container->removeDefinition('test.http_client.middleware');
  }

  /**
   * Test actual call to Edge API that IsValidEdgeCredentials() uses.
   */
  public function testIsValidEdgeCredentialsEdgeApi() {
    $url = $this->endpoint . '/o/' . $this->organization;
    $response = $this->http_client->get($url, [
      'auth' => [$this->orgadmin_email, $this->orgadmin_password],
      'headers' => ['Accept' => 'application/json'],
    ]);

    $body = json_decode($response->getBody());
    $this->assertTrue(isset($body->name), 'Edge org entity should contain "name" attribute.');
    $this->assertEquals($this->organization, $body->name, 'Edge org name attribute should match org being called in url.');
  }

  /**
   * Test Edge API response/request for doesRoleExist()
   */
  public function testDoesRoleExist() {
    // Role should not exist.
    $url = $this->endpoint . '/o/' . $this->organization . '/userroles/' . self::TEST_ROLE_NAME;

    $response = $this->http_client->get($url, [
      'http_errors' => FALSE,
      'auth' => [$this->orgadmin_email, $this->orgadmin_password],
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);
    $this->assertEquals('404', $response->getStatusCode(), 'Role that does not exist should return 404.');

  }

  /**
   * Test Edge API for creating role and setting permissions.
   */
  public function testCreateEdgeRoleAndSetPermissions() {

    $url = $this->endpoint . '/o/' . $this->organization . '/userroles';
    $response = $this->http_client->post($url, [
      'body' => json_encode([
        'role' => [self::TEST_ROLE_NAME],
      ]),
      'auth' => [$this->orgadmin_email, $this->orgadmin_password],
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);
    $this->assertEquals('201', $response->getStatusCode(), 'Role should be created.');

    // Add permissions to this role.
    $url = $this->endpoint . '/o/' . $this->organization . '/userroles/' . self::TEST_ROLE_NAME . '/permissions';
    $body = json_encode([
      'path' => '/developers',
      'permissions' => ['get', 'put', 'delete'],
    ]);
    $response = $this->http_client->post($url, [
      'body' => $body,
      'auth' => [$this->orgadmin_email, $this->orgadmin_password],
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);
    $this->assertEquals('201', $response->getStatusCode(), 'Permission on role should be created.');
  }

}
