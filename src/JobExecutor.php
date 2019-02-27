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

namespace Drupal\apigee_edge;

use Drupal\apigee_edge\Job\Job;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;

/**
 * Job executor service.
 */
class JobExecutor implements JobExecutorInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The 'apigee_edge_job' queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * JobExecutor constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time interface.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue factory.
   */
  public function __construct(Connection $connection, TimeInterface $time, QueueFactory $queue_factory) {
    $this->connection = $connection;
    $this->time = $time;
    $this->queue = $queue_factory->get('apigee_edge_job');
  }

  /**
   * Ensures that a job exists with a given status.
   *
   * @param \Drupal\apigee_edge\Job\Job $job
   *   Job object.
   * @param int $status
   *   Job status.
   */
  protected function ensure(Job $job, int $status) {
    if ($job->getStatus() !== $status) {
      $job->setStatus($status);
      $this->save($job);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(Job $job) {
    $now = $this->time->getCurrentTime();
    $jobdata = serialize($job);
    $fields = [
      'status' => $job->getStatus(),
      'job' => $jobdata,
      'updated' => $now,
      'tag' => $job->getTag(),
    ];
    $this->connection->merge('apigee_edge_job')
      ->key('id', $job->getId())
      ->insertFields([
        'id' => $job->getId(),
        'created' => $now,
      ] + $fields)
      ->updateFields($fields)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $id): ?Job {
    $query = $this->connection->select('apigee_edge_job', 'j')
      ->fields('j', ['job']);
    $query->condition('id', $id);
    $jobdata = $query->execute()->fetchField();

    return $jobdata ? unserialize($jobdata) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function select(?string $tag = NULL): ?Job {
    // TODO handle race conditions.
    $query = $this->connection->select('apigee_edge_job', 'j')
      ->fields('j', ['job'])
      ->orderBy('updated')
      ->range(0, 1);
    $query->condition('status', [Job::IDLE, Job::RESCHEDULED], 'IN');
    if ($tag !== NULL) {
      $query->condition('tag', $tag);
    }
    $jobdata = $query->execute()->fetchField();

    if ($jobdata) {
      /** @var \Drupal\apigee_edge\Job\Job $job */
      $job = unserialize($jobdata);
      $this->ensure($job, Job::SELECTED);

      return $job;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function call(Job $job, bool $update = TRUE) {
    $this->ensure($job, Job::RUNNING);
    try {
      $result = $job->execute();
      $job->setStatus($result ? Job::IDLE : Job::FINISHED);
    }
    catch (\Exception $ex) {
      watchdog_exception('apigee_edge_job', $ex);
      $job->recordException($ex);
      $job->setStatus($job->shouldRetry($ex) && $job->consumeRetry() ? Job::RESCHEDULED : Job::FAILED);
    }
    finally {
      if ($update) {
        $this->save($job);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cast(Job $job) {
    $this->save($job);
    $this->queue->createItem(['tag' => $job->getTag()]);
  }

  /**
   * {@inheritdoc}
   */
  public function countJobs(?string $tag = NULL, ?array $statuses = NULL): int {
    $query = $this->connection->select('apigee_edge_job', 'j');

    if ($tag !== NULL) {
      $query->condition('tag', $tag);
    }

    if ($statuses !== NULL) {
      $query->condition('status', $statuses, 'IN');
    }

    return (int) $query
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
