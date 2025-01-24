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

namespace Drupal\Tests\apigee_edge\Unit\EventSubscriber;

use Apigee\Edge\Exception\ApiException;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\MainContent\HtmlRenderer;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\apigee_edge\Controller\ErrorPageController;
use Drupal\apigee_edge\EventSubscriber\EdgeExceptionSubscriber;
use Prophecy\Argument;
use Prophecy\Prophet;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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
   * The logger mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $logger;

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
   * Class Resolver service.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The available main content renderer services, keyed per format.
   *
   * @var array
   */
  protected $mainContentRenderers;

  /**
   * The getResponseForException mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $getResponseForExceptionEvent;

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
    $this->exception = new ApiException("API response error message.");

    $this->logger = $this->prophet->prophesize(LoggerInterface::class);

    $this->messenger = $this->prophet->prophesize(MessengerInterface::class);

    $response = $this->prophet->prophesize(Response::class);

    $this->mainContentRenderers = ['html' => 'main_content_renderer.html'];

    $htmlRenderer = $this->prophet->prophesize(HtmlRenderer::class);
    $htmlRenderer->renderResponse(Argument::cetera())
      ->willReturn($response->reveal());

    $errorPageController = $this->prophet->prophesize(ErrorPageController::class);
    $errorPageController->render()
      ->willReturn([]);
    $errorPageController->getPageTitle()
      ->willReturn('');

    $this->classResolver = $this->prophet->prophesize(ClassResolverInterface::class);
    $this->classResolver->getInstanceFromDefinition(Argument::is($this->mainContentRenderers['html']))
      ->willReturn($htmlRenderer->reveal());
    $this->classResolver->getInstanceFromDefinition(Argument::is(ErrorPageController::class))
      ->willReturn($errorPageController->reveal());

    $this->routeMatch = $this->prophet->prophesize(RouteMatchInterface::class);

    // Drupal 9 / Symfony 4.x and up.
    $kernel = $this->prophet->prophesize(HttpKernelInterface::class);
    $request = Request::create('/test');
    $this->getResponseForExceptionEvent = new ExceptionEvent($kernel->reveal(), $request, HttpKernelInterface::MAIN_REQUEST, new \Exception());
  }

  /**
   * Test OnException method will show errors.
   *
   * When error_page_debug_messages config is set, the exception message
   * should be displayed.
   */
  public function testOnExceptionErrorsOn() {

    // Config will return true when checked.
    $config_error_page = $this->prophet->prophesize(Config::class);
    $config_error_page
      ->get(Argument::is('error_page_debug_messages'))
      ->shouldBeCalledTimes(1)
      ->willReturn(TRUE);
    $this->configFactory = $this->prophet->prophesize(ConfigFactoryInterface::class);
    $this->configFactory
      ->get(Argument::is('apigee_edge.error_page'))
      ->willReturn($config_error_page->reveal());

    // Error should be displayed since show debug messages config is on.
    $this->messenger->addError(Argument::is($this->exception->getMessage()))
      ->shouldBeCalledTimes(1);

    $edge_exception_subscriber = new EdgeExceptionSubscriber(
      $this->logger->reveal(),
      $this->configFactory->reveal(),
      $this->messenger->reveal(),
      $this->classResolver->reveal(),
      $this->routeMatch->reveal(),
      $this->mainContentRenderers
    );

    $edge_exception_subscriber->onException($this->getResponseForExceptionEvent);
  }

  /**
   * Test OnExceptionErrors method will not show errors.
   *
   * When error_page_debug_messages config is FALSE, the exception message
   * should be not be displayed.
   */
  public function testOnExceptionErrorsOff() {

    // Config will return false when checked.
    $config_error_page = $this->prophet->prophesize(Config::class);
    $config_error_page
      ->get(Argument::is('error_page_debug_messages'))
      ->shouldBeCalledTimes(1)
      ->willReturn(FALSE);
    $this->configFactory = $this->prophet->prophesize(ConfigFactoryInterface::class);
    $this->configFactory
      ->get(Argument::is('apigee_edge.error_page'))
      ->willReturn($config_error_page->reveal());

    // Messenger should not be adding error since show debug messages is false.
    $this->messenger->addError(Argument::type('string'))
      ->shouldNotBeCalled();

    $edge_exception_subscriber = new EdgeExceptionSubscriber(
      $this->logger->reveal(),
      $this->configFactory->reveal(),
      $this->messenger->reveal(),
      $this->classResolver->reveal(),
      $this->routeMatch->reveal(),
      $this->mainContentRenderers
    );

    $edge_exception_subscriber->onException($this->getResponseForExceptionEvent);
  }

}
