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

namespace Drupal\apigee_edge_test\EventSubscriber\MockApiRequestSubscriber;

use Drupal\apigee_edge_test\Event\ApiRequestEvent;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\State;
use GuzzleHttp\Psr7;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Returns mock API responses from the Drupal State key-value store.
 *
 * It uses the State as a FIFO storage.
 */
final class ApiResponseFromState implements EventSubscriberInterface {

  /**
   * The settings that controls whether mocked requests get captured or not.
   *
   * @var string
   */
  public const SETTINGS_CAPTURE_REQUESTS = 'apigee_edge_test_state_response_provider_capture_requests';

  /**
   * The key that this provider uses as storage for mock responses.
   *
   * @var string
   */
  private const STATE_KEY_RESPONSE_STORAGE = 'apigee_edge_test.mock_response_provider.state.response_storage';

  /**
   * The key that this provider uses as storage to captured mocked requests.
   *
   * @var string
   */
  private const STATE_KEY_REQUEST_STORAGE = 'apigee_edge_test.mock_response_provider.state.request_storage';

  /**
   * The state backend.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * Settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  private $settings;

  /**
   * ApiResponseFromState constructor.
   *
   * @param \Drupal\Core\State\State $state
   *   The state backend.
   * @param \Drupal\Core\Site\Settings $settings
   *   Settings.
   */
  public function __construct(State $state, Settings $settings) {
    $this->state = $state;
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ApiRequestEvent::EVENT_NAME => 'handle',
    ];
  }

  /**
   * Returns a mock API response from the state storage if it is not empty.
   *
   * @param \Drupal\apigee_edge_test\Event\ApiRequestEvent $event
   *   The API request event.
   */
  public function handle(ApiRequestEvent $event): void {
    $queued_responses = $this->getQueuedResponses();
    if (!empty($queued_responses)) {
      /** @var \Psr\Http\Message\ResponseInterface $response */
      $response = array_shift($queued_responses);
      $response = Psr7\parse_response($response);
      $event->setResponse($response);
      $this->state->set(static::STATE_KEY_RESPONSE_STORAGE, $queued_responses);
      if ($this->settings->get(static::SETTINGS_CAPTURE_REQUESTS, FALSE)) {
        $request_storage = $this->state->get(static::STATE_KEY_REQUEST_STORAGE, []);
        $request_storage[] = Psr7\str($event->getRequest());
        $this->state->set(static::STATE_KEY_REQUEST_STORAGE, $request_storage);
      }
      // Do not call other providers because either a request-independent API
      // response is available in the storage or there should not be any
      // response in the storage.
      $event->stopPropagation();
    }
  }

  /**
   * Adds an API response to the queue.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   A mock API response.
   */
  public function queueResponse(ResponseInterface $response): void {
    // We must clear the static cache here because items can stack in there in
    // tests.
    $this->state->resetCache();
    $queued_responses = $this->state->get(static::STATE_KEY_RESPONSE_STORAGE, []);
    $queued_responses[] = Psr7\str($response);
    $this->state->set(static::STATE_KEY_RESPONSE_STORAGE, $queued_responses);
  }

  /**
   * Returns queued API responses.
   *
   * @return \Psr\Http\Message\ResponseInterface[]
   *   Mock API responses in the queue.
   */
  public function getQueuedResponses(): array {
    return $this->state->get(static::STATE_KEY_RESPONSE_STORAGE, []);
  }

  /**
   * Clears queued mock API responses and captured mocked API requests.
   */
  public function clear(): void {
    $this->state->delete(static::STATE_KEY_RESPONSE_STORAGE);
    $this->state->delete(static::STATE_KEY_REQUEST_STORAGE);
  }

  /**
   * Returns mocked API requests that got mocked if capturing was enabled.
   *
   * @return \Psr\Http\Message\RequestInterface[]
   *   Captured API requests that got mocked.
   */
  public function getMockedRequests(): array {
    return array_map(static function (string $item) {
      return Psr7\parse_request($item);
    }, $this->state->get(static::STATE_KEY_REQUEST_STORAGE, []));
  }

}
