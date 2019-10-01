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
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\EventSubscriber\DefaultExceptionHtmlSubscriber;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * Handles uncaught ApiExceptions.
 *
 * Redirects the user to the Edge error page if an uncaught
 * SDK-level ApiException event appears in the HttpKernel component.
 */
final class EdgeExceptionSubscriber extends DefaultExceptionHtmlSubscriber {

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
   * EdgeExceptionSubscriber constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The HTTP kernel.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   * @param \Symfony\Component\Routing\Matcher\UrlMatcherInterface $access_unaware_router
   *   A router implementation which does not check access.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(HttpKernelInterface $http_kernel, LoggerInterface $logger, RedirectDestinationInterface $redirect_destination, UrlMatcherInterface $access_unaware_router, ConfigFactoryInterface $config_factory, MessengerInterface $messenger) {
    parent::__construct($http_kernel, $logger, $redirect_destination, $access_unaware_router);
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
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
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The exception event.
   */
  public function onException(GetResponseForExceptionEvent $event) {
    if ($event->getException() instanceof ApiException || $event->getException()->getPrevious() instanceof ApiException) {
      $context = Error::decodeException($event->getException());
      $this->logger->critical('@message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
      $this->makeSubrequest($event, '/api-communication-error', Response::HTTP_SERVICE_UNAVAILABLE);

      // Display additional debug messages.
      if ($this->configFactory->get('apigee_edge.error_page')->get('error_page_debug_messages')) {
        $this->messenger->addError($event->getException()->getMessage());
      }
    }
  }

}
