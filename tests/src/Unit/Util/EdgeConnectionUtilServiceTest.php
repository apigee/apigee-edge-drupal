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

use Drupal\apigee_edge\Util\EdgeConnectionUtilService;
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
 * Test EdgeConnectionUtilService.
 *
 * @group apigee_edge
 */
class EdgeConnectionUtilServiceTest extends UnitTestCase {

  protected $base_url = 'http://api.apigee.com';
  protected $email = 'noreply@apigee.com';
  protected $password = 'secret';
  protected $org = 'org1';
  protected $role_name = 'drupal_connect_role';

  protected $http_client;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->http_client = $this->prophesize(Client::class);
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

    $this->http_client
      ->get(Argument::type('string'), Argument::type('array'))
      ->willReturn($response->reveal());

    $edge_connection_util_service = new EdgeConnectionUtilService($this->http_client->reveal());
    $is_valid_creds = $edge_connection_util_service->isValidEdgeCredentials($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->base_url);
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

    $this->http_client
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

    $edge_connection_util_service = new EdgeConnectionUtilService($this->http_client->reveal());
    $is_valid_creds = $edge_connection_util_service->isValidEdgeCredentials($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->base_url);
    $this->assertEquals(TRUE, $is_valid_creds, 'Credentials are not valid, should return false.');
  }

  /**
   * Should make a call to create role if does not exist.
   */
  public function testCreateEdgeRoleWhenRoleDoesNotExistTest() {

    $io = $this->prophesize(StyleInterface::class);
    $io->success(Argument::exact('Connected to Edge org ' . $this->org . '.'))->shouldBeCalledTimes(1);
    $io->success(Argument::containingString('Role ' . $this->role_name . ' is configured.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Role ' . $this->role_name . ' already exists.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Setting permissions on role ' . $this->role_name . '.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('/'))->shouldBeCalledTimes(12);

    $body = '{ "name": "' . $this->org . '" }';
    $response = $this->prophesize(Response::class);
    $response->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn($body);

    $this->http_client
      ->get(Argument::exact($this->base_url . '/o/' . $this->org), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response->reveal());

    $this->http_client
      ->get(Argument::exact($this->base_url . '/o/' . $this->org . '/userroles/' . $this->role_name), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response->reveal());

    $this->http_client
      ->post(Argument::type('string'), Argument::type('array'))
      ->willReturn($response->reveal());

    $edge_connection_util_service = new EdgeConnectionUtilService($this->http_client->reveal());
    $edge_connection_util_service->createEdgeRoleForDrupal($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->base_url, $this->role_name);
  }

  /**
   * Do not try to create role if it already exists.
   */
  public function testCreateEdgeRoleForDrupalWhenRoleExistsTest() {
    $io = $this->prophesize(StyleInterface::class);
    $io->success(Argument::exact('Connected to Edge org ' . $this->org . '.'))->shouldBeCalledTimes(1);
    $io->success(Argument::containingString('Role ' . $this->role_name . ' is configured.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Role ' . $this->role_name . ' already exists.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('Setting permissions on role ' . $this->role_name . '.'))->shouldBeCalledTimes(1);
    $io->text(Argument::containingString('/'))->shouldBeCalledTimes(12);

    $response_org = $this->prophesize(Response::class);
    $response_org->getBody()
      ->shouldBeCalledTimes(1)
      ->willReturn('{ "name": "' . $this->org . '" }');

    $this->http_client
      ->get(Argument::exact($this->base_url . '/o/' . $this->org), Argument::type('array'))
      ->shouldBeCalledTimes(1)
      ->willReturn($response_org->reveal());

    $response_user_role = $this->prophesize(Response::class);

    $this->http_client
      ->get(Argument::exact($this->base_url . '/o/' . $this->org . '/userroles/' . $this->role_name), Argument::type('array'))
      ->willReturn($response_user_role->reveal());

    $this->http_client
      ->post(Argument::exact($this->base_url . '/o/' . $this->org . '/userroles/' . $this->role_name . '/permissions'), Argument::type('array'))
      ->shouldBeCalledTimes(12);

    $edge_connection_util_service = new EdgeConnectionUtilService($this->http_client->reveal());
    $edge_connection_util_service->createEdgeRoleForDrupal($io->reveal(), [$this, 'mockDt'], $this->org, $this->email, $this->password, $this->base_url, $this->role_name);
  }

  /**
   * Validate doesRoleExist works when role does not exist.
   */
  public function testDoesRoleExistTrue() {

    $this->http_client
      ->get(Argument::cetera())
      ->shouldBeCalledTimes(1)
      ->willReturn(NULL);

    $edge_connection_util_service = new EdgeConnectionUtilService($this->http_client->reveal());
    $does_role_exist = $edge_connection_util_service->doesRoleExist($this->org, $this->email, $this->password, $this->base_url, $this->role_name);
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

    $this->http_client
      ->get(Argument::type('string'), Argument::type('array'))
      ->willThrow($exception);

    $edge_connection_util_service = new EdgeConnectionUtilService($this->http_client->reveal());
    $does_role_exist = $edge_connection_util_service->doesRoleExist($this->org, $this->email, $this->password, $this->base_url, $this->role_name);

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
    $this->http_client
      ->get(Argument::type('string'), Argument::type('array'))
      ->willThrow($exception);

    $edge_connection_util_service = new EdgeConnectionUtilService($this->http_client->reveal());
    $edge_connection_util_service->doesRoleExist($this->org, $this->email, $this->password, $this->base_url, $this->role_name);
  }

  /**
   * Make sure method outputs more info for error codes.
   */
  public function testHandleHttpClientExceptions0Code() {
    $exception = $this->prophesize(TransferException::class);
    $io = $this->prophesize(StyleInterface::class);
    $io->error(Argument::containingString('Error connecting to Apigee Edge'))->shouldBeCalledTimes(1);
    $io->note(Argument::containingString('Your system may not be able to connect'))->shouldBeCalledTimes(1);

    $edge_connection_util_service = new EdgeConnectionUtilService($this->http_client->reveal());
    $edge_connection_util_service->handleHttpClientExceptions($exception->reveal(), $io->reveal(), [$this, 'mockDt'], 'http://api.apigee.com/test', $this->org, $this->email);
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

    $edge_connection_util_service = new EdgeConnectionUtilService($this->http_client->reveal());
    $edge_connection_util_service->handleHttpClientExceptions($exception, $io->reveal(), [$this, 'mockDt'], 'http://api.apigee.com/test', $this->org, $this->email);
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
    $io->note(Argument::containingString('the url ' . $this->base_url . '/test' . ' does not seem to be a valid Apigee Edge endpoint.'))->shouldBeCalledTimes(1);

    $edge_connection_util_service = new EdgeConnectionUtilService($this->http_client->reveal());
    $edge_connection_util_service->handleHttpClientExceptions($exception, $io->reveal(), [$this, 'mockDt'], $this->base_url . '/test', $this->org, $this->email);
  }

  /**
   * Test setDefaultPermissions method.
   *
   * @throws \ReflectionException
   */
  public function testSetDefaultPermissions() {
    $edge_connection_util_service = new EdgeConnectionUtilService($this->http_client->reveal());
    $io = $this->prophesize(StyleInterface::class);

    $this->http_client->post(Argument::type('string'), Argument::type('array'))->shouldBeCalledTimes(12);

    // Make method under test not private.
    $edge_connection_util_service_reflection = new ReflectionClass($edge_connection_util_service);
    $method_set_default_permissions = $edge_connection_util_service_reflection->getMethod('setDefaultPermissions');
    $method_set_default_permissions->setAccessible(TRUE);

    $args = [
      $io->reveal(),
      [$this, 'mockDt'],
      $this->org,
      $this->email,
      $this->password,
      $this->base_url,
      $this->role_name,
    ];
    $method_set_default_permissions->invokeArgs($edge_connection_util_service, $args);
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
  public function mockDt(string $message, array $context): string {
    // Do the same thing as Drush dt().
    return StringUtils::interpolate($message, $context);
  }

}
