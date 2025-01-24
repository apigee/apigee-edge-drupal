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

namespace Drupal\Tests\apigee_edge\Unit\Command\Util;

use Apigee\Edge\ClientInterface as ApigeeClientInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\apigee_edge\Command\Util\ApigeeEdgeManagementCliService;
use Drupal\apigee_edge\Command\Util\ApigeeEdgeManagementCliServiceInterface;
use Drush\Utils\StringUtils;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Prophecy\Argument;
use Prophecy\Prophet;
use Psr\Http\Message\RequestInterface;
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
   * The Prophet class.
   *
   * @var \Prophecy\Prophet
   */
  private $prophet;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->prophet = new Prophet();
    $this->httpClient = $this->prophet->prophesize(Client::class);
  }

  /**
   * Call createEdgeRoleForDrupal with null base URL to test default base URL.
   */
  public function testCreateEdgeRoleForDrupalCustomRoleAndBaseUrl() {
    // Output to user should show role created and permissions set.
    $io = $this->prophet->prophesize(StyleInterface::class);
    $io->success(Argument::exact('Connected to Edge org ' . $this->org . '.'))->shouldBeCalledTimes(1);
    $io->success(Argument::containingString('Role ' . $this->roleName . ' is configured.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Role ' . $this->roleName . ' does not exist. Creating role.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Setting permissions on role ' . $this->roleName . '.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('/'))->shouldBeCalledTimes(12);

    // Org should exist.
    $response_org = $this->prophet->prophesize(Response::class);
    $response_org->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn(Utils::streamFor('{ "name": "' . $this->org . '" }'));
    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response_org->reveal());

    // The role should not exist yet in system.
    $request_role = $this->prophet->prophesize(RequestInterface::class);
    $response_role = $this->prophet->prophesize(Response::class);
    $response_role->getStatusCode()->willReturn(404);
    $exception = new ClientException('Forbidden', $request_role->reveal(), $response_role->reveal());
    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles/' . $this->roleName), Argument::type('array'))
      ->willThrow($exception);

    // The role should be created.
    $this->httpClient
      ->post(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles'), Argument::type('array'))
      ->shouldBeCalledTimes(1);

    // The permissions should be set properly.
    $this->httpClient
      ->post(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles/' . $this->roleName . '/permissions'), Argument::type('array'))
      ->shouldBeCalledTimes(12);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->createEdgeRoleForDrupal($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->baseUrl, $this->roleName, FALSE);
  }

  /**
   * Pass null role name to test using default role name.
   */
  public function testCreateEdgeRoleForDrupalDefaultRoleAndBaseUrl() {
    // Output to user should show role created and permissions set.
    $io = $this->prophet->prophesize(StyleInterface::class);
    $io->success(Argument::exact('Connected to Edge org ' . $this->org . '.'))->shouldBeCalledTimes(1);
    $io->success(Argument::containingString('Role ' . ApigeeEdgeManagementCliServiceInterface::DEFAULT_ROLE_NAME . ' is configured.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Role ' . ApigeeEdgeManagementCliServiceInterface::DEFAULT_ROLE_NAME . ' does not exist'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Setting permissions on role ' . ApigeeEdgeManagementCliServiceInterface::DEFAULT_ROLE_NAME . '.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('/'))->shouldBeCalledTimes(12);

    // Org should exist.
    $response_org = $this->prophet->prophesize(Response::class);
    $response_org->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn(Utils::streamFor('{ "name": "' . $this->org . '" }'));
    $this->httpClient
      ->get(Argument::exact(ApigeeClientInterface::EDGE_ENDPOINT . '/o/' . $this->org), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response_org->reveal());

    // The role should not exist yet in system.
    $request_role = $this->prophet->prophesize(RequestInterface::class);
    $response_role = $this->prophet->prophesize(Response::class);
    $response_role->getStatusCode()->willReturn(404);
    $exception = new ClientException('Forbidden', $request_role->reveal(), $response_role->reveal());
    $this->httpClient
      ->get(Argument::exact(ApigeeClientInterface::EDGE_ENDPOINT . '/o/' . $this->org . '/userroles/' . ApigeeEdgeManagementCliServiceInterface::DEFAULT_ROLE_NAME), Argument::type('array'))
      ->willThrow($exception);

    // The role should be created.
    $this->httpClient
      ->post(Argument::exact(ApigeeClientInterface::EDGE_ENDPOINT . '/o/' . $this->org . '/userroles'), Argument::type('array'))
      ->shouldBeCalledTimes(1);

    // The permissions should be set.
    $this->httpClient
      ->post(Argument::exact(ApigeeClientInterface::EDGE_ENDPOINT . '/o/' . $this->org . '/userroles/' . ApigeeEdgeManagementCliServiceInterface::DEFAULT_ROLE_NAME . '/permissions'), Argument::type('array'))
      ->shouldBeCalledTimes(12);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->createEdgeRoleForDrupal($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, NULL, NULL, FALSE);
  }

  /**
   * Allow role to get modified w/force option.
   */
  public function testCreateEdgeRoleForDrupalWhenRoleExistsTestWithForceFlag() {
    // Expected to output error if role does not exist.
    $io = $this->prophet->prophesize(StyleInterface::class);
    $io->success(Argument::exact('Connected to Edge org ' . $this->org . '.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Setting permissions on role ' . $this->roleName . '.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('/'))->shouldBeCalledTimes(12);
    $io->success(Argument::containingString('Role ' . $this->roleName . ' is configured.'))->shouldBeCalledTimes(1);

    // Return organization info.
    $response_org = $this->prophet->prophesize(Response::class);
    $response_org->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn(Utils::streamFor('{ "name": "' . $this->org . '" }'));
    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response_org->reveal());

    // Return existing role.
    $response_user_role = $this->prophet->prophesize(Response::class);
    $response_user_role->getBody()->willReturn(Utils::streamFor('{ "name": "' . $this->roleName . '" }'));
    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles/' . $this->roleName), Argument::type('array'))
      ->willReturn($response_user_role->reveal());

    // The role should NOT be created since is already exists.
    $this->httpClient
      ->post(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles'), Argument::type('array'))
      ->shouldNotBeCalled();

    // The permissions should be set.
    $this->httpClient
      ->post(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles/' . $this->roleName . '/permissions'), Argument::type('array'))
      ->shouldBeCalledTimes(12);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->createEdgeRoleForDrupal($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->baseUrl, $this->roleName, TRUE);
  }

  /**
   * If force parameter is not passed in, do not mess with a role that exists.
   */
  public function testCreateEdgeRoleForDrupalWhenRoleExistsTestNoForceFlag() {
    // Expected to output error if role does not exist.
    $io = $this->prophet->prophesize(StyleInterface::class);
    $io->success(Argument::exact('Connected to Edge org ' . $this->org . '.'))->shouldBeCalledTimes(1);
    $io->error(Argument::containingString('Role ' . $this->roleName . ' already exists.'))->shouldBeCalledTimes(1);
    $io->note(Argument::containingString('Run with --force option'))->shouldBeCalled();

    // Return organization info.
    $response_org = $this->prophet->prophesize(Response::class);
    $response_org->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn(Utils::streamFor('{ "name": "' . $this->org . '" }'));
    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response_org->reveal());

    // Return existing role.
    $response_user_role = $this->prophet->prophesize(Response::class);
    $response_user_role->getBody()->willReturn(Utils::streamFor('{ "name": "' . $this->roleName . '" }'));
    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles/' . $this->roleName), Argument::type('array'))
      ->willReturn($response_user_role->reveal());

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->createEdgeRoleForDrupal($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->baseUrl, $this->roleName, FALSE);
  }

  /**
   * Test isValidEdgeCredentials() bad endpoint response.
   */
  public function testIsValidEdgeCredentialsBadEndpoint() {
    // Mimic a invalid response for the call to get org details.
    $body = "<h1>not json</h1>";
    $response = $this->prophet->prophesize(Response::class);
    $response->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn(Utils::streamFor($body));

    // The user should see an error message.
    $io = $this->prophet->prophesize(StyleInterface::class);
    $io->error(Argument::containingString('Unable to parse response from GET'))
      ->shouldBeCalledTimes(1);
    $this->httpClient
      ->get(Argument::type('string'), Argument::type('array'))
      ->willReturn($response->reveal());

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $is_valid_creds = $apigee_edge_management_cli_service->isValidEdgeCredentials($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->baseUrl);

    // Assert return that creds are false.
    $this->assertEquals(FALSE, $is_valid_creds, 'Credentials are not valid, should return false.');
  }

  /**
   * Test isValidEdgeCredentials() unauthorized response.
   */
  public function testIsValidEdgeCredentialsUnauthorized() {
    // Invalid password returns unauthorized 403.
    $request_role = $this->prophet->prophesize(RequestInterface::class);
    $response_role = $this->prophet->prophesize(Response::class);
    $response_role->getStatusCode()->willReturn(403);
    $exception = new ClientException('Unauthorized', $request_role->reveal(), $response_role->reveal());
    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org), Argument::type('array'))
      ->willThrow($exception)
      ->shouldBeCalledTimes(1);

    // The user should see an error message.
    $io = $this->prophet->prophesize(StyleInterface::class);
    $io->error(Argument::containingString('Error connecting to Apigee Edge'))
      ->shouldBeCalledTimes(1);
    $io->note(Argument::containingString('may not have the orgadmin role for Apigee Edge org'))
      ->shouldBeCalledTimes(1);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $is_valid_creds = $apigee_edge_management_cli_service->isValidEdgeCredentials($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->baseUrl);
    $this->assertEquals(FALSE, $is_valid_creds, 'Credentials are not valid, should return false.');
  }

  /**
   * Should return true if creds are valid.
   */
  public function testIsValidEdgeCredentialsValid() {
    // Org should exist.
    $response_org = $this->prophet->prophesize(Response::class);
    $response_org->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn(Utils::streamFor('{ "name": "' . $this->org . '" }'));
    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response_org->reveal());

    // Errors should not be called.
    $io = $this->prophet->prophesize(StyleInterface::class);
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

    // Assertions.
    $this->assertEquals(TRUE, $is_valid_creds, 'Credentials are not valid, should return false.');
  }

  /**
   * Validate doesRoleExist works when role does not exist.
   */
  public function testDoesRoleExistTrue() {
    // Return existing role.
    $response_user_role = $this->prophet->prophesize(Response::class);
    $response_user_role->getBody()->willReturn(Utils::streamFor('{ "name": "' . $this->roleName . '" }'));
    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles/' . $this->roleName), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response_user_role->reveal());

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $does_role_exist = $apigee_edge_management_cli_service->doesRoleExist($this->org, $this->email, $this->password, $this->baseUrl, $this->roleName);

    // Assert returned true.
    $this->assertEquals(TRUE, $does_role_exist, 'Method doesRoleExist() should return true when role exists.');
  }

  /**
   * Validate doesRoleExist works when role exists.
   */
  public function testDoesRoleExistNotTrue() {
    // The role should not exist in system.
    $request_role = $this->prophet->prophesize(RequestInterface::class);
    $response_role = $this->prophet->prophesize(Response::class);
    $response_role->getStatusCode()->willReturn(404);
    $exception = new ClientException('Forbidden', $request_role->reveal(), $response_role->reveal());
    $this->httpClient
      ->get(Argument::exact($this->baseUrl . '/o/' . $this->org . '/userroles/' . $this->roleName), Argument::type('array'))
      ->willThrow($exception);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $does_role_exist = $apigee_edge_management_cli_service->doesRoleExist($this->org, $this->email, $this->password, $this->baseUrl, $this->roleName);

    // Assert returns false.
    $this->assertEquals(FALSE, $does_role_exist, 'Method doesRoleExist() should return false when role exists.');
  }

  /**
   * Validate when exception thrown function works correctly.
   */
  public function testDoesRoleExistServerErrorThrown() {
    // Http client throws exception if network or server error happens.
    $request = $this->prophet->prophesize(RequestInterface::class);
    $response = $this->prophet->prophesize(Response::class);
    $response->getStatusCode()->willReturn(500);
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
    // Error message should output to user.
    $io = $this->prophet->prophesize(StyleInterface::class);
    $io->error(Argument::containingString('Error connecting to Apigee Edge'))->shouldBeCalledTimes(1);
    $io->note(Argument::containingString('Your system may not be able to connect'))->shouldBeCalledTimes(1);

    // Create network error.
    $exception = $this->prophet->prophesize(TransferException::class);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->handleHttpClientExceptions($exception->reveal(), $io->reveal(), [$this, 'mockDt'], 'http://api.apigee.com/test', $this->org, $this->email);
  }

  /**
   * Make sure method outputs more info for error codes.
   */
  public function testHandleHttpClientExceptions401Code() {
    // Server returns 401 unauthorized.
    $request = $this->prophet->prophesize(RequestInterface::class);
    $response = $this->prophet->prophesize(Response::class);
    $response->getStatusCode()->willReturn(401);
    $exception = new ClientException('Unauthorized', $request->reveal(), $response->reveal());

    // Expect user friendly message displayed about error.
    $io = $this->prophet->prophesize(StyleInterface::class);
    $io->error(Argument::containingString('Error connecting to Apigee Edge'))->shouldBeCalledTimes(1);
    $io->note(Argument::exact('Your username or password is invalid.'))->shouldBeCalledTimes(1);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->handleHttpClientExceptions($exception, $io->reveal(), [$this, 'mockDt'], 'http://api.apigee.com/test', $this->org, $this->email);
  }

  /**
   * Make sure method outputs more info for error codes.
   */
  public function testHandleHttpClientExceptions403Code() {
    // Server returns 403 forbidden.
    $request = $this->prophet->prophesize(RequestInterface::class);
    $response = $this->prophet->prophesize(Response::class);
    $response->getStatusCode()->willReturn(403);
    $exception = new ClientException('Forbidden', $request->reveal(), $response->reveal());

    // Expect error messages.
    $io = $this->prophet->prophesize(StyleInterface::class);
    $io->error(Argument::containingString('Error connecting to Apigee Edge'))->shouldBeCalledTimes(1);
    $io->note(Argument::containingString('User ' . $this->email . ' may not have the orgadmin role'))->shouldBeCalledTimes(1);

    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service->handleHttpClientExceptions($exception, $io->reveal(), [$this, 'mockDt'], 'http://api.apigee.com/test', $this->org, $this->email);
  }

  /**
   * Make sure method outputs more info for error codes.
   */
  public function testHandleHttpClientExceptions302Code() {
    // Return a 302 redirection response, which Apigee API would not do.
    $request = $this->prophet->prophesize(RequestInterface::class);
    $response = $this->prophet->prophesize(Response::class);
    $response->getStatusCode()->willReturn(302);
    $exception = new ClientException('Forbidden', $request->reveal(), $response->reveal());

    // User should see error message.
    $io = $this->prophet->prophesize(StyleInterface::class);
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
    // The permissions POST call will be made 12 times.
    $this->httpClient->post(Argument::type('string'), Argument::type('array'))->shouldBeCalledTimes(12);

    // Make method under test not private.
    $apigee_edge_management_cli_service = new ApigeeEdgeManagementCliService($this->httpClient->reveal());
    $apigee_edge_management_cli_service_reflection = new \ReflectionClass($apigee_edge_management_cli_service);
    $method_set_default_permissions = $apigee_edge_management_cli_service_reflection->getMethod('setDefaultPermissions');
    $method_set_default_permissions->setAccessible(TRUE);

    // Create input params.
    $io = $this->prophet->prophesize(StyleInterface::class);
    $args = [
      $io->reveal(),
      [$this, 'mockDt'],
      $this->org,
      $this->email,
      $this->password,
      $this->baseUrl,
      $this->roleName,
    ];

    // Make call.
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
