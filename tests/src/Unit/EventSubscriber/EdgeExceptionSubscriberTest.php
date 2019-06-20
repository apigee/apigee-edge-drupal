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

namespace Drupal\apigee_edge\Unit\EventSubscriber;

use Apigee\Edge\Exception\ApiException;
use Drupal\apigee_edge\EventSubscriber\EdgeExceptionSubscriber;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Config\Config;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Test EdgeExceptionSubscriber.
 *
 * @group apigee_edge
 */
class EdgeExceptionSubscriberTest extends UnitTestCase {

  protected $exception;
  protected $http_kernel;
  protected $logger;
  protected $redirect_destination;
  protected $access_unaware_router;
  protected $config_factory;
  protected $messenger;
  protected $get_response_for_exception_event;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->exception = new ApiException("API response error message.");

    $this->http_kernel = $this->prophesize(HttpKernelInterface::class);
    $this->logger = $this->prophesize(LoggerInterface::class);
    $this->redirect_destination = $this->prophesize(RedirectDestinationInterface::class);

    $request = $this->prophesize(RequestContext::class);
    $this->access_unaware_router = $this->prophesize(UrlMatcherInterface::class);
    $this->access_unaware_router->getContext(Argument::any())->willReturn($request->reveal());

    $this->messenger = $this->prophesize(MessengerInterface::class);

    $request = $this->prophesize(Request::class);

    // Set empty objects so that clone on request object works.
    $request->query = new stdClass();
    $request->request = new stdClass();
    $request->attributes = new stdClass();
    $request->cookies = new stdClass();
    $request->files = new stdClass();
    $request->server = new stdClass();
    $request->headers = new stdClass();

    $this->get_response_for_exception_event = $this->prophesize(GetResponseForExceptionEvent::class);
    $this->get_response_for_exception_event->getRequest()
      ->willReturn($request->reveal());
    $this->get_response_for_exception_event->getException()
      ->willReturn($this->exception);

  }

  /**
   * Test OnException method will show errors.
   *
   * When error_page_debug_messages config is set, the exception message
   * should be displayed.
   */
  public function testOnExceptionErrorsOn() {

    // Config will return true when checked.
    $config_error_page = $this->prophesize(Config::class);
    $config_error_page
      ->get(Argument::is('error_page_debug_messages'))
      ->shouldBeCalledTimes(1)
      ->willReturn(TRUE);
    $this->config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $this->config_factory
      ->get(Argument::is('apigee_edge.error_page'))
      ->willReturn($config_error_page->reveal());

    // Error should be displayed since show debug messages config is on.
    $this->messenger->addError(Argument::is($this->exception->getMessage()))
      ->shouldBeCalledTimes(1);

    $edge_exception_subscriber = new EdgeExceptionSubscriber(
      $this->http_kernel->reveal(),
      $this->logger->reveal(),
      $this->redirect_destination->reveal(),
      $this->access_unaware_router->reveal(),
      $this->config_factory->reveal(),
      $this->messenger->reveal()
    );

    $edge_exception_subscriber->onException($this->get_response_for_exception_event->reveal());
  }

  /**
   * Test OnExceptionErrors method will not show errors.
   *
   * When error_page_debug_messages config is FALSE, the exception message
   * should be not be displayed.
   */
  public function testOnExceptionErrorsOff() {

    // Config will return false when checked.
    $config_error_page = $this->prophesize(Config::class);
    $config_error_page
      ->get(Argument::is('error_page_debug_messages'))
      ->shouldBeCalledTimes(1)
      ->willReturn(FALSE);
    $this->config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $this->config_factory
      ->get(Argument::is('apigee_edge.error_page'))
      ->willReturn($config_error_page->reveal());

    // Messenger should not be adding error since show debug messages is false.
    $this->messenger->addError(Argument::type('string'))
      ->shouldNotBeCalled();

    $edge_exception_subscriber = new EdgeExceptionSubscriber(
      $this->http_kernel->reveal(),
      $this->logger->reveal(),
      $this->redirect_destination->reveal(),
      $this->access_unaware_router->reveal(),
      $this->config_factory->reveal(),
      $this->messenger->reveal()
    );

    $edge_exception_subscriber->onException($this->get_response_for_exception_event->reveal());
  }

}
