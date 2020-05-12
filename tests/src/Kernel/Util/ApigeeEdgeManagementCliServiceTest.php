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

namespace Drupal\Tests\apigee_edge\Kernel\Util;

use Apigee\Edge\Exception\ClientErrorException;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\apigee_mock_api_client\Traits\ApigeeMockApiClientHelperTrait;

/**
 * ApigeeEdgeManagementCliService Edge tests.
 *
 * Make sure Edge API works as expected for the ApigeeEdgeManagementCliService.
 *
 * These tests validate Edge API request/responses needed for
 * ApigeeEdgeManagementCliService are valid.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class ApigeeEdgeManagementCliServiceTest extends KernelTestBase implements ServiceModifierInterface {

  use ApigeeMockApiClientHelperTrait;

  /**
   * Indicates this test class is mock API client ready.
   *
   * @var bool
   */
  protected static $mock_api_client_ready = TRUE;

  protected const TEST_ROLE_NAME = 'temp_role';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'apigee_edge',
    'key',
    'apigee_mock_api_client',
  ];

  /**
   * Apigee API endpoint.
   *
   * @var array|false|string
   */
  protected $endpoint;

  /**
   * Apigee Edge organization.
   *
   * @var array|false|string
   */
  protected $organization;

  /**
   * Email of an account with the organization admin Apigee role.
   *
   * @var array|false|string
   */
  protected $orgadminEmail;

  /**
   * The password of the orgadmin account.
   *
   * @var array|false|string
   */
  protected $orgadminPassword;

  /**
   * A GuzzleHttp\Client object.
   *
   * @var object|null
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('user');

    $this->apigeeTestHelperSetup();

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
    $this->orgadminEmail = getenv('APIGEE_EDGE_USERNAME');
    $this->orgadminPassword = getenv('APIGEE_EDGE_PASSWORD');

    /** @var \GuzzleHttp\Client $client */
    $this->httpClient = $this->sdkConnector->getClient();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $url = $this->endpoint . '/o/' . $this->organization . '/userroles/' . self::TEST_ROLE_NAME;
    try {
      $this->stack->queueMockResponse('get_not_found');
      $response = $this->httpClient->get($url);

      if ($response->getStatusCode() == 200) {
        $url = $this->endpoint . '/o/' . $this->organization . '/userroles/' . self::TEST_ROLE_NAME;
        $this->httpClient->delete($url);
      }
    }
    catch (\Exception $exception) {
      watchdog_exception('apigee_edge', $exception);
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
    $this->stack->queueMockResponse([
      'get_organization' => [
        'org_name' => $this->organization,
      ],
    ]);
    $url = $this->endpoint . '/o/' . $this->organization;
    $response = $this->httpClient->get($url);

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
    $errorMsg = 'Userrole temp_role does not exist';

    $this->stack->queueMockResponse([
      'get_not_found'  => [
        'status_code' => 404,
        'message' => $errorMsg,
      ],
    ]);
    $this->expectException(ClientErrorException::class);
    $this->expectExceptionMessage($errorMsg);
    $response = $this->httpClient->get($url);
    $this->assertEquals('404', $response->getStatusCode(), 'Role that does not exist should return 404.');
  }

  /**
   * Test Edge API for creating role and setting permissions.
   */
  public function testCreateEdgeRoleAndSetPermissions() {
    $this->stack->queueMockResponse(['no_content' => ['status_code' => 201]]);
    $url = $this->endpoint . '/o/' . $this->organization . '/userroles';
    $response = $this->httpClient->post($url, json_encode([
      'role' => [self::TEST_ROLE_NAME],
    ]));
    $this->assertEquals('201', $response->getStatusCode(), 'Role should be created.');

    // Add permissions to this role.
    $this->stack->queueMockResponse(['no_content' => ['status_code' => 201]]);
    $url = $this->endpoint . '/o/' . $this->organization . '/userroles/' . self::TEST_ROLE_NAME . '/permissions';
    $body = json_encode([
      'path' => '/developers',
      'permissions' => ['get', 'put', 'delete'],
    ]);
    $response = $this->httpClient->post($url, $body);
    $this->assertEquals('201', $response->getStatusCode(), 'Permission on role should be created.');
  }

}
