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

namespace Drupal\apigee_edge_test\Event;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * A mock-able API request event to the Apigee backend.
 *
 * An event subscriber (mock API request subscriber) can decide whether it
 * reacts to an API request or not. Every API request subscriber gets called for
 * an API request - unless stopPropagation() method had been called on an event
 * by a subscriber which aborts the execution of further subscribers - multiple
 * subscriber can perform an action for a request but only one mock API response
 * gets return to an API request.
 *
 * @see \Drupal\apigee_edge_test\HttpClientMiddleware\MockApiRequestEventDispatcher
 */
final class ApiRequestEvent extends Event {

  /**
   * The event name.
   *
   * @var string
   */
  public const EVENT_NAME = 'apigee_edge_test.mock_http_request_event';

  /**
   * A mock API response if any.
   *
   * @var \Psr\Http\Message\ResponseInterface|null
   */
  protected $response;

  /**
   * The API request.
   *
   * @var \Psr\Http\Message\RequestInterface
   */
  private $request;

  /**
   * ApiRequestEvent constructor.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The API request.
   */
  public function __construct(RequestInterface $request) {
    $this->request = $request;
  }

  /**
   * The API request.
   *
   * @return \Psr\Http\Message\RequestInterface
   *   The API request.
   */
  public function getRequest(): RequestInterface {
    return $this->request;
  }

  /**
   * The mock API response, if any.
   *
   * @return \Psr\Http\Message\ResponseInterface|null
   *   The mock API response for the request or null.
   */
  public function getResponse(): ?ResponseInterface {
    return $this->response;
  }

  /**
   * The mock API response for a request.
   *
   * @param \Psr\Http\Message\ResponseInterface|null $response
   *   The mock API response or null if there is no response.
   */
  public function setResponse(?ResponseInterface $response): void {
    $this->response = $response;
  }

}
