<?php

/*
 * Copyright 2020 Google Inc.
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

namespace Drupal\apigee_mock_client;

use Apigee\MockClient\MatchableResultInterface;
use Apigee\MockClient\MockStorageInterface;
use Apigee\MockClient\Psr7\SerializableMessageWrapper;
use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\State\StateInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;

/**
 * A mock storage for functional tests.
 */
class DatabaseMockStorage implements MockStorageInterface {

  public const STATE_DEFAULT_RESULT_ID = 'apigee_mock_client.db_mock_storage.default';
  public const STATE_REQUESTS_ID = 'apigee_mock_client.db_mock_storage.requests';
  public const STATE_MATCHABLE_RESULT_ID = 'apigee_mock_client.db_mock_storage.responses';

  /**
   * The queue to use to store the responses.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The state API.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * DatabaseMockStorage constructor.
   *
   * @param \Drupal\Core\Queue\QueueDatabaseFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state API.
   */
  public function __construct(QueueDatabaseFactory $queue_factory, StateInterface $state) {
    $this->queue = $queue_factory->get('apigee_mock_client.db_mock_storage');
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function default() {
    $default = $this->state->get(static::STATE_DEFAULT_RESULT_ID);

    return $default instanceof SerializableMessageWrapper ? $default->getMessage() : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefault($default = NULL): MockStorageInterface {
    // Make sure the default result is serializable.
    $default = $default instanceof MessageInterface ? new SerializableMessageWrapper($default) : $default;

    $this->state->set(static::STATE_DEFAULT_RESULT_ID, $default);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function add($result): MockStorageInterface {
    $result = $result instanceof MessageInterface ? new SerializableMessageWrapper($result) : $result;

    $this->queue->createItem($result);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function claim() {
    // Delete the item from the queue if it was successfully claimed.
    if ($item = $this->queue->claimItem()) {
      $this->queue->deleteItem($item);
    }
    // Get the result.
    $result = $item ? $item->data : NULL;
    return $result instanceof SerializableMessageWrapper ? $result->getMessage() : $result;
  }

  /**
   * {@inheritdoc}
   */
  public function responseCount(): int {
    return $this->queue->numberOfItems();
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): MockStorageInterface {
    $this->queue->deleteQueue();
    $this->setDefault();
    $this->state->delete(static::STATE_REQUESTS_ID);
    $this->state->delete(static::STATE_MATCHABLE_RESULT_ID);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addRequest(RequestInterface $request): MockStorageInterface {
    $requests = $this->requests();
    $requests[] = $request;
    $this->state->set(static::STATE_REQUESTS_ID, array_map(function ($item) {
      return new SerializableMessageWrapper($item);
    }, $requests));

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function lastRequest(): ?RequestInterface {
    $requests = $this->requests();

    return !empty($requests) ? end($requests) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function requests(): array {
    if ($requests = $this->state->get(static::STATE_REQUESTS_ID)) {
      $requests = array_map(function ($wrapped_request) {
        return $wrapped_request->getMessage();
      }, $requests);
    }

    return $requests ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function totalRequests(): int {
    return count($this->requests());
  }

  /**
   * {@inheritdoc}
   */
  public function addMatchableResult(MatchableResultInterface $matchableResult): MockStorageInterface {
    $matchers = $this->state->get(static::STATE_MATCHABLE_RESULT_ID);
    $matchers = $matchers ?? [];
    $matchers[] = $matchableResult;
    $this->state->set(static::STATE_MATCHABLE_RESULT_ID, $matchers);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function matchableResults(): array {
    $matchable_results = $this->state->get(static::STATE_MATCHABLE_RESULT_ID);

    return $matchable_results ?? [];
  }

}
