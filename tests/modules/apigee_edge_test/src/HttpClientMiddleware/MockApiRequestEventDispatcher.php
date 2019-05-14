<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge_test\HttpClientMiddleware;

use Drupal\apigee_edge_test\Event\ApiRequestEvent;
use Drupal\apigee_edge_test\SDKConnector;
use GuzzleHttp\Promise\FulfilledPromise;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Allows to mock Apigee API requests.
 */
final class MockApiRequestEventDispatcher {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * MockApiRequestEventDispatcher constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function __invoke() {
    return function (callable $handler) {
      return function (RequestInterface $request, array $options) use ($handler) {
        if ($request->hasHeader(SDKConnector::HEADER)) {
          $event = new ApiRequestEvent($request);
          $this->eventDispatcher->dispatch(ApiRequestEvent::EVENT_NAME, $event);

          if ($event->getResponse()) {
            return new FulfilledPromise($event->getResponse());
          }
        }

        // No mock API subscriber provided a mock response for this HTTP
        // request. Let's call the real API backend.
        return $handler($request, $options);
      };
    };
  }

}
