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
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles uncaught ApiExceptions.
 *
 * Redirects the user to the Edge error page if an uncaught
 * SDK-level ApiException event appears in the HttpKernel component.
 */
class EdgeExceptionSubscriber implements EventSubscriberInterface {

  /**
   * The URL generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructs EdgeExceptionSubscriber.
   *
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator service.
   */
  public function __construct(UrlGeneratorInterface $url_generator) {
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION][] = 'onException';
    return $events;
  }

  /**
   * Redirects user to the Edge error page.
   *
   * Redirects user to the Edge error page if the
   * uncaught exception is an instance of ApiException.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The exception event.
   */
  public function onException(GetResponseForExceptionEvent $event) {
    if ($event->getException() instanceof ApiException || $event->getException()->getPrevious() instanceof ApiException) {
      $url = $this->urlGenerator->generateFromRoute('apigee_edge.error_page');
      $event->setResponse(new RedirectResponse($url));
    }
  }

}
