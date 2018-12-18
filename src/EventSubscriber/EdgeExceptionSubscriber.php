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
use Drupal\Core\EventSubscriber\DefaultExceptionHtmlSubscriber;
use Drupal\Core\Utility\Error;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * Handles uncaught ApiExceptions.
 *
 * Redirects the user to the Edge error page if an uncaught
 * SDK-level ApiException event appears in the HttpKernel component.
 */
final class EdgeExceptionSubscriber extends DefaultExceptionHtmlSubscriber {

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
    }
  }

}
