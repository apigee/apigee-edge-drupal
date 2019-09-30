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

namespace Drupal\Tests\apigee_edge\Unit;

use Drupal\apigee_edge\Command\Util\ApigeeEdgeManagementCliService;
use Drupal\apigee_edge\Command\Util\ApigeeEdgeManagementCliServiceInterface;
use Drupal\Tests\UnitTestCase;
use Drush\Utils\StringUtils;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Test ApigeeEdgeManagementCliService.
 *
 * @group apigee_edge
 */
class ApigeeEdgeManagementCliServiceTest extends UnitTestCase {

  /**
   * Test base url.
   *
   * @var string
   */
  protected $baseUrl = 'http://api.apigee.com';

  /**
   * Test email.
   *
   * @var string
   */
  protected $email = 'noreply@apigee.com';

  /**
   * Test password.
   *
   * @var string
   */
  protected $password = 'secret';

  /**
   * Test org.
   *
   * @var string
   */
  protected $org = 'org1';

  /**
   * Test role name.
   *
   * @var string
   */
  protected $roleName = 'drupal_connect_role';

  /**
   * Mock HTTP Client.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->httpClient = $this->prophesize(Client::class);
  }

  /**
   * Call createEdgeRoleForDrupal with null base URL to test default base URL.
   */
  public function testCreateEdgeRoleForDrupalDefaultBaseUrl() {
    $io = $this->prophesize(StyleInterface::class);
    $io->success(Argument::exact('Connected to Edge org ' . $this->org . '.'))->shouldBeCalledTimes(1);
    $io->success(Argument::containingString('Role ' . $this->roleName . ' is configured.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Role ' . $this->roleName . ' already exists.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Setting permissions on role ' . $this->roleName . '.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('/'))->shouldBeCalledTimes(12);

    $response_org = $this->prophesize(Response::class);
    $response_org->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn('{ "name": "' . $this->org . '" }');

    $this->httpClient
      ->get(Argument::exact(ApigeeEdgeManagementCliServiceInterface::DEFAULT_BASE_URL . '/o/' . $this->org), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response_org->reveal());

    $response_user_role = $this->prophesize(Response::class);

    $this->httpClient
      ->get(Argument::exact(ApigeeEdgeManagementCliServiceInterface::DEFAULT_BASE_URL . '/o/' . $this->org . '/userroles/' . $this->roleName), Argument::type('array'))
      ->willReturn($response_user_role->reveal());

    $this->httpClient
      ->post(Argument::exact(ApigeeEdgeManagementCliServiceInterface::DEFAULT_BASE_URL . '/o/' . $this->org . '/userroles/' . $this->roleName . '/permissions'), Argument::type('array'))
      ->shouldBeCalledTimes(12);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->createEdgeRoleForDrupal($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, NULL, $this->roleName);
  }

  /**
   * Pass null role name to test using default role name.
   */
  public function testCreateEdgeRoleForDrupalDefaultRoleName() {
    $io = $this->prophesize(StyleInterface::class);
    $io->success(Argument::exact('Connected to Edge org ' . $this->org . '.'))->shouldBeCalledTimes(1);
    $io->success(Argument::containingString('Role ' . ApigeeEdgeManagementCliServiceInterface::DEFAULT_ROLE_NAME . ' is configured.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Role ' . ApigeeEdgeManagementCliServiceInterface::DEFAULT_ROLE_NAME . ' already exists.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Setting permissions on role ' . ApigeeEdgeManagementCliServiceInterface::DEFAULT_ROLE_NAME . '.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('/'))->shouldBeCalledTimes(12);

    $response_org = $this->prophesize(Response::class);
    $response_org->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn('{ "name": "' . $this->org . '" }');

    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response_org->reveal());

    $response_user_role = $this->prophesize(Response::class);

    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles/' . ApigeeEdgeManagementCliServiceInterface::DEFAULT_ROLE_NAME), Argument::type('array'))
      ->willReturn($response_user_role->reveal());

    $this->httpClient
      ->post(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles/' . ApigeeEdgeManagementCliServiceInterface::DEFAULT_ROLE_NAME . '/permissions'), Argument::type('array'))
      ->shouldBeCalledTimes(12);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->createEdgeRoleForDrupal($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->baseUrl, NULL);
  }

  /**
   * Should return true if creds are valid.
   */
  public function testIsValidEdgeCredentialsNotValid() {
    $body = "<h1>not json</h1>";

    $response = $this->prophesize(Response::class);
    $response->getBody()
      ->shouldBeCalledTimes(2)
      ->willReturn($body);

    $io = $this->prophesize(StyleInterface::class);
    $io->error(Argument::containingString('Unable to parse response from Apigee Edge into JSON'))
      ->shouldBeCalledTimes(1);
    $io->section(Argument::type('string'))
      ->shouldBeCalledTimes(1);
    $io->text(Argument::type('string'))
      ->shouldBeCalledTimes(1);

    $this->httpClient
      ->get(Argument::type('string'), Argument::type('array'))
      ->willReturn($response->reveal());

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $is_valid_creds = $apigee_edge_management_cli_service->isValidEdgeCredentials($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->baseUrl);
    $this->assertEquals(FALSE, $is_valid_creds, 'Credentials are not valid, should return false.');
  }

  /**
   * Should return true if creds are valid.
   */
  public function testIsValidEdgeCredentialsValid() {
    $body = '{ "name": "' . $this->org . '" }';

    $response = $this->prophesize(Response::class);
    $response->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn($body);

    $this->httpClient
      ->get(Argument::type('string'), Argument::type('array'))
      ->willReturn($response->reveal());

    $io = $this->prophesize(StyleInterface::class);
    $io->error(Argument::type('string'))
      ->shouldNotBeCalled();
    $io->section(Argument::type('string'))
      ->shouldNotBeCalled();
    $io->text(Argument::type('string'))
      ->shouldNotBeCalled();
    $io->success(Argument::type('string'))
      ->shouldBeCalled();

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $is_valid_creds = $apigee_edge_management_cli_service->isValidEdgeCredentials($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->baseUrl);
    $this->assertEquals(TRUE, $is_valid_creds, 'Credentials are not valid, should return false.');
  }

  /**
   * Should make a call to create role if does not exist.
   */
  public function testCreateEdgeRoleWhenRoleDoesNotExistTest() {

    $io = $this->prophesize(StyleInterface::class);
    $io->success(Argument::exact('Connected to Edge org ' . $this->org . '.'))->shouldBeCalledTimes(1);
    $io->success(Argument::containingString('Role ' . $this->roleName . ' is configured.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Role ' . $this->roleName . ' already exists.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Setting permissions on role ' . $this->roleName . '.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('/'))->shouldBeCalledTimes(12);

    $body = '{ "name": "' . $this->org . '" }';
    $response = $this->prophesize(Response::class);
    $response->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn($body);

    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response->reveal());

    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles/' . $this->roleName), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response->reveal());

    $this->httpClient
      ->post(Argument::type('string'), Argument::type('array'))
      ->willReturn($response->reveal());

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->createEdgeRoleForDrupal($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->baseUrl, $this->roleName);
  }

  /**
   * Recreate role if it already exists.
   */
  public function testCreateEdgeRoleForDrupalWhenRoleExistsTest() {
    $io = $this->prophesize(StyleInterface::class);
    $io->success(Argument::exact('Connected to Edge org ' . $this->org . '.'))->shouldBeCalledTimes(1);
    $io->success(Argument::containingString('Role ' . $this->roleName . ' is configured.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Role ' . $this->roleName . ' already exists.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Setting permissions on role ' . $this->roleName . '.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('/'))->shouldBeCalledTimes(12);

    $response_org = $this->prophesize(Response::class);
    $response_org->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn('{ "name": "' . $this->org . '" }');

    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response_org->reveal());

    $response_user_role = $this->prophesize(Response::class);

    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles/' . $this->roleName), Argument::type('array'))
      ->willReturn($response_user_role->reveal());

    $this->httpClient
      ->post(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles/' . $this->roleName . '/permissions'), Argument::type('array'))
      ->shouldBeCalledTimes(12);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->createEdgeRoleForDrupal($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->baseUrl, $this->roleName);
  }

  /**
   * Validate doesRoleExist works when role does not exist.
   */
  public function testDoesRoleExistTrue() {

    $this->httpClient
      ->get(Argument::cetera())
      ->shouldBeCalledTimes(1)
      ->willReturn(NULL);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $does_role_exist = $apigee_edge_management_cli_service->doesRoleExist($this->org, $this->email, $this->password, $this->baseUrl, $this->roleName);
    $this->assertEquals(TRUE, $does_role_exist, 'Method doesRoleExist() should return true when role exists.');
  }

  /**
   * Validate doesRoleExist works when role exists.
   */
  public function testDoesRoleExistNotTrue() {

    $request = $this->prophesize(RequestInterface::class);
    $response = $this->prophesize(Response::class);
    $response->getStatusCode()->willReturn(404);

    // Http client throws exception when role does not exist.
    $exception = new ClientException('Role does not exist.', $request->reveal(), $response->reveal());

    $this->httpClient
      ->get(Argument::type('string'), Argument::type('array'))
      ->willThrow($exception);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $does_role_exist = $apigee_edge_management_cli_service->doesRoleExist($this->org, $this->email, $this->password, $this->baseUrl, $this->roleName);

    $this->assertEquals(FALSE, $does_role_exist, 'Method doesRoleExist() should return false when role exists.');
  }

  /**
   * Validate when exception thrown function works correctly.
   */
  public function testDoesRoleExistExceptionThrown() {
    $request = $this->prophesize(RequestInterface::class);
    $response = $this->prophesize(Response::class);
    $response->getStatusCode()->willReturn(500);

    // Http client throws exception if network or server error happens.
    $exception = new ServerException('Server error.', $request->reveal(), $response->reveal());
    $this->expectException(ServerException::class);
    $this->httpClient
      ->get(Argument::type('string'), Argument::type('array'))
      ->willThrow($exception);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->doesRoleExist($this->org, $this->email, $this->password, $this->baseUrl, $this->roleName);
  }

  /**
   * Make sure method outputs more info for error codes.
   */
  public function testHandleHttpClientExceptions0Code() {
    $exception = $this->prophesize(TransferException::class);
    $io = $this->prophesize(StyleInterface::class);
    $io->error(Argument::containingString('Error connecting to Apigee Edge'))->shouldBeCalledTimes(1);
    $io->note(Argument::containingString('Your system may not be able to connect'))->shouldBeCalledTimes(1);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->handleHttpClientExceptions($exception->reveal(), $io->reveal(), [$this, 'mockDt'], 'http://api.apigee.com/test', $this->org, $this->email);
  }

  /**
   * Make sure method outputs more info for error codes.
   */
  public function testHandleHttpClientExceptions401Code() {
    $request = $this->prophesize(RequestInterface::class);
    $response = $this->prophesize(Response::class);
    $response->getStatusCode()->willReturn(401);

    $exception = new ClientException('Forbidden', $request->reveal(), $response->reveal());

    $io = $this->prophesize(StyleInterface::class);
    $io->error(Argument::containingString('Error connecting to Apigee Edge'))->shouldBeCalledTimes(1);
    $io->note(Argument::exact('Your username or password is invalid.'))->shouldBeCalledTimes(1);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->handleHttpClientExceptions($exception, $io->reveal(), [$this, 'mockDt'], 'http://api.apigee.com/test', $this->org, $this->email);
  }

  /**
   * Make sure method outputs more info for error codes.
   */
  public function testHandleHttpClientExceptions403Code() {
    $request = $this->prophesize(RequestInterface::class);
    $response = $this->prophesize(Response::class);
    $response->getStatusCode()->willReturn(403);

    $exception = new ClientException('Forbidden', $request->reveal(), $response->reveal());

    $io = $this->prophesize(StyleInterface::class);
    $io->error(Argument::containingString('Error connecting to Apigee Edge'))->shouldBeCalledTimes(1);
    $io->note(Argument::containingString('User ' . $this->email . ' may not have the orgadmin role'))->shouldBeCalledTimes(1);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->handleHttpClientExceptions($exception, $io->reveal(), [$this, 'mockDt'], 'http://api.apigee.com/test', $this->org, $this->email);
  }

  /**
   * Make sure method outputs more info for error codes.
   */
  public function testHandleHttpClientExceptions302Code() {
    $request = $this->prophesize(RequestInterface::class);
    $response = $this->prophesize(Response::class);
    $response->getStatusCode()->willReturn(302);

    $exception = new ClientException('Forbidden', $request->reveal(), $response->reveal());

    $io = $this->prophesize(StyleInterface::class);
    $io->error(Argument::containingString('Error connecting to Apigee Edge'))->shouldBeCalledTimes(1);
    $io->note(Argument::containingString('the url ' . $this->baseUrl . '/test' . ' does not seem to be a valid Apigee Edge endpoint.'))->shouldBeCalledTimes(1);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->handleHttpClientExceptions($exception, $io->reveal(), [$this, 'mockDt'], $this->baseUrl . '/test', $this->org, $this->email);
  }

  /**
   * Test setDefaultPermissions method.
   *
   * @throws \ReflectionException
   */
  public function testSetDefaultPermissions() {
    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $io = $this->prophesize(StyleInterface::class);

    $this->httpClient->post(Argument::type('string'), Argument::type('array'))->shouldBeCalledTimes(12);

    // Make method under test not private.
    $apigee_edge_management_cli_service_reflection = new ReflectionClass($apigee_edge_management_cli_service);
    $method_set_default_permissions = $apigee_edge_management_cli_service_reflection->getMethod('setDefaultPermissions');
    $method_set_default_permissions->setAccessible(TRUE);

    $args = [
      $io->reveal(),
      [$this, 'mockDt'],
      $this->org,
      $this->email,
      $this->password,
      $this->baseUrl,
      $this->roleName,
    ];
    $method_set_default_permissions->invokeArgs($apigee_edge_management_cli_service, $args);
  }

  /**
   * Mock translation method.
   *
   * @param string $message
   *   The message to return.
   * @param array $context
   *   The context of vars to replace.
   *
   * @return string
   *   The message with context.
   */
  public function mockDt(string $message, array $context = []): string {
    // Do the same thing as Drush dt().
    return StringUtils::interpolate($message, $context);
  }

}
