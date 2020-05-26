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

namespace Drupal\apigee_edge\EventSubscriber;

use Apigee\Edge\Exception\ApiException;
use Drupal\apigee_edge\Controller\ErrorPageController;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Route;

/**
 * Handles uncaught ApiExceptions.
 *
 * Redirects the user to the Edge error page if an uncaught
 * SDK-level ApiException event appears in the HttpKernel component.
 */
final class EdgeExceptionSubscriber implements EventSubscriberInterface {

  /**
   * The logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * EdgeExceptionSubscriber constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param array $main_content_renderers
   *   The available main content renderer service IDs, keyed by format.
   */
  public function __construct(LoggerInterface $logger, ConfigFactoryInterface $config_factory, MessengerInterface $messenger, ClassResolverInterface $class_resolver, RouteMatchInterface $route_match, array $main_content_renderers) {
    $this->logger = $logger;
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->classResolver = $class_resolver;
    $this->routeMatch = $route_match;
    $this->mainContentRenderers = $main_content_renderers;
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority() {
    return 1024;
  }

  /**
   * Displays the Edge connection error page.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent|\Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The exception event.
   */
  public function onException($event) {
    $exception = ($event instanceof ExceptionEvent) ? $event->getThrowable() : $event->getException();
    if ($exception instanceof ApiException || $exception->getPrevious() instanceof ApiException) {
      $context = Error::decodeException($exception);
      $this->logger->critical('@message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);

      $controller = $this->classResolver->getInstanceFromDefinition(ErrorPageController::class);
      $content = [
        '#title' => $controller->getPageTitle(),
        'content' => $controller->render(),
      ];

      $routeMatch = new RouteMatch('apigee_edge.error_page', new Route('/api-communication-error'));
      $renderer = $this->classResolver->getInstanceFromDefinition($this->mainContentRenderers['html']);

      /* @var \Symfony\Component\HttpFoundation\Response $response */
      $response = $renderer->renderResponse($content, $event->getRequest(), $routeMatch);
      $response->setStatusCode(503);

      // Display additional debug messages.
      if ($this->configFactory->get('apigee_edge.error_page')->get('error_page_debug_messages')) {
        $this->messenger->addError($exception->getMessage());
      }

      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION][] = ['onException', static::getPriority()];
    return $events;
  }

}
