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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

  /**
   * The API Exception class.
   *
   * @var \Apigee\Edge\Exception\ApiException
   */
  protected $exception;

  /**
   * The HTTP Kernel mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $httpKernel;

  /**
   * The logger mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $logger;

  /**
   * The redirect destination mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $redirectDestination;

  /**
   * The access unaware router mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $accessUnawareRouter;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configFactory;

  /**
   * The messenger mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $messenger;

  /**
   * The getResponseForException mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $getResponseForExceptionEvent;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->exception = new ApiException("API response error message.");

    $this->httpKernel = $this->prophesize(HttpKernelInterface::class);
    $this->httpKernel->handle(Argument::any(), Argument::any())->willReturn(new Response());
    $this->logger = $this->prophesize(LoggerInterface::class);
    $this->redirectDestination = $this->prophesize(RedirectDestinationInterface::class);
    $this->redirectDestination->getAsArray()->willReturn([]);

    $request = $this->prophesize(RequestContext::class);
    $this->accessUnawareRouter = $this->prophesize(UrlMatcherInterface::class);
    $this->accessUnawareRouter->getContext(Argument::any())->willReturn($request->reveal());
    $this->accessUnawareRouter->setContext(Argument::any())->willReturn();
    $this->accessUnawareRouter->match(Argument::any())->willReturn([]);

    $this->messenger = $this->prophesize(MessengerInterface::class);

    $this->getResponseForExceptionEvent = $this->prophesize(GetResponseForExceptionEvent::class);
    $this->getResponseForExceptionEvent->getRequest()
      ->willReturn(new Request());
    $this->getResponseForExceptionEvent->getException()
      ->willReturn($this->exception);
    $this->getResponseForExceptionEvent->setResponse(Argument::any())
      ->willReturn();
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
    $this->configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $this->configFactory
      ->get(Argument::is('apigee_edge.error_page'))
      ->willReturn($config_error_page->reveal());

    // Error should be displayed since show debug messages config is on.
    $this->messenger->addError(Argument::is($this->exception->getMessage()))
      ->shouldBeCalledTimes(1);

    $edge_exception_subscriber = new EdgeExceptionSubscriber(
      $this->httpKernel->reveal(),
      $this->logger->reveal(),
      $this->redirectDestination->reveal(),
      $this->accessUnawareRouter->reveal(),
      $this->configFactory->reveal(),
      $this->messenger->reveal()
    );

    $edge_exception_subscriber->onException($this->getResponseForExceptionEvent->reveal());
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
    $this->configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $this->configFactory
      ->get(Argument::is('apigee_edge.error_page'))
      ->willReturn($config_error_page->reveal());

    // Messenger should not be adding error since show debug messages is false.
    $this->messenger->addError(Argument::type('string'))
      ->shouldNotBeCalled();

    $edge_exception_subscriber = new EdgeExceptionSubscriber(
      $this->httpKernel->reveal(),
      $this->logger->reveal(),
      $this->redirectDestination->reveal(),
      $this->accessUnawareRouter->reveal(),
      $this->configFactory->reveal(),
      $this->messenger->reveal()
    );

    $edge_exception_subscriber->onException($this->getResponseForExceptionEvent->reveal());
  }

}
