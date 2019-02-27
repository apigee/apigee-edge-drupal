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

namespace Drupal\apigee_edge\Job;

/**
 * Defines the Job class.
 */
abstract class Job {

  /**
   * Job is waiting to be picked up by a worker.
   *
   * @var int
   */
  public const IDLE = 0;

  /**
   * Job failed, waiting to be retried.
   *
   * @var int
   */
  public const RESCHEDULED = 1;

  /**
   * Job is claimed by a worker, but not running yet.
   *
   * @var int
   */
  public const SELECTED = 2;

  /**
   * Job is running.
   *
   * @var int
   */
  public const RUNNING = 3;

  /**
   * Job is failed, and it won't be retried.
   *
   * @var int
   */
  public const FAILED = 4;

  /**
   * Job is finished successfully.
   *
   * @var int
   */
  public const FINISHED = 5;

  /**
   * Job statuses.
   *
   * @var string[]
   */
  protected const ALL_STATUSES = [
    self::IDLE,
    self::RESCHEDULED,
    self::SELECTED,
    self::RUNNING,
    self::FAILED,
    self::FINISHED,
  ];

  /**
   * Exception storage.
   *
   * @var array
   */
  protected $exceptions = [];

  /**
   * Messages storage.
   *
   * @var string[]
   */
  protected $messages = [];

  /**
   * Job ID.
   *
   * @var string
   *   UUID of the job.
   */
  private $id;

  /**
   * The tag of the job.
   *
   * @var string
   */
  private $tag;

  /**
   * Remaining retries.
   *
   * @var int
   */
  protected $retry = 0;

  /**
   * Job status.
   *
   * @var int
   */
  protected $status = self::IDLE;

  /**
   * Job constructor.
   */
  public function __construct() {
    /** @var \Drupal\Component\Uuid\UuidInterface $uuid_service */
    $uuid_service = \Drupal::service('uuid');
    $this->id = $uuid_service->generate();
  }

  /**
   * Gets the job id.
   *
   * @return string
   *   UUID of the job.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Gets the job tag.
   *
   * The job tag can be used to group multiple jobs together.
   *
   * @return string
   *   The job tag.
   */
  public function getTag(): string {
    return $this->tag;
  }

  /**
   * Sets the job tag.
   *
   * @param string $tag
   *   The job tag.
   */
  public function setTag(string $tag) {
    $this->tag = $tag;
  }

  /**
   * Gets the status of the job.
   *
   * @return int
   *   The job's status.
   */
  public function getStatus(): int {
    return $this->status;
  }

  /**
   * Sets the status of the job.
   *
   * @param int $status
   *   The job's status.
   */
  public function setStatus(int $status) {
    if (!in_array($status, self::ALL_STATUSES)) {
      throw new \LogicException('Invalid status');
    }

    $this->status = $status;
  }

  /**
   * Adds an exception to the exception storage.
   *
   * @param \Exception $exception
   *   The exception.
   */
  public function recordException(\Exception $exception) {
    $this->exceptions[] = [
      'code' => $exception->getCode(),
      'message' => $exception->getMessage(),
      'file' => $exception->getFile(),
      'line' => $exception->getLine(),
      'trace' => $exception->getTraceAsString(),
    ];
  }

  /**
   * Gets all stored exception data.
   *
   * @return array
   *   Array of the stored exceptions.
   */
  public function getExceptions(): array {
    return $this->exceptions;
  }

  /**
   * Adds a message to the message storage.
   *
   * @param string $message
   *   The message.
   */
  public function recordMessage(string $message) {
    $this->messages[] = $message;
  }

  /**
   * Gets all stored messages.
   *
   * @return string[]
   *   Array of the stored messages.
   */
  public function getMessages(): array {
    return $this->messages;
  }

  /**
   * Consumes a retry.
   *
   * @return bool
   *   Whether the job can be rescheduled.
   */
  public function consumeRetry(): bool {
    if ($this->retry > 0) {
      $this->retry--;
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Whether this job should be retried when an exception is thrown.
   *
   * @param \Exception $exception
   *   The thrown exception.
   *
   * @return bool
   *   TRUE if the job should be retried.
   */
  public function shouldRetry(\Exception $exception): bool {
    return TRUE;
  }

  /**
   * Executes this job.
   *
   * This function should be called only by the JobExecutor.
   *
   * @return bool
   *   Whether the job is incomplete. Returning TRUE here means that the job
   *   will be rescheduled.
   */
  abstract public function execute(): bool;

  /**
   * Returns this job's visual representation.
   *
   * @return array
   *   The render array.
   */
  abstract public function renderArray(): array;

  /**
   * Returns this job's textual representation.
   *
   * @return string
   *   The string representation of the job.
   */
  abstract public function __toString(): string;

}
