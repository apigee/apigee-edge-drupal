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

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Queue\QueueFactory;

/**
 * Job executor service.
 */
class JobExecutor {

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
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   Queue factory.
   */
  public function __construct(Connection $connection, TimeInterface $time, QueueFactory $queueFactory) {
    $this->connection = $connection;
    $this->time = $time;
    $this->queue = $queueFactory->get('apigee_edge_job');
  }

  /**
   * Ensures that a job exists with a given status.
   *
   * @param \Drupal\apigee_edge\Job $job
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
   * Saves a job.
   *
   * @param \Drupal\apigee_edge\Job $job
   *   Job object.
   *
   * @throws \Exception
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
   * Loads a job from the database.
   *
   * @param string $id
   *   Job id.
   *
   * @return \Drupal\apigee_edge\Job|null
   *   Loaded job object or null if it does not exit.
   */
  public function load(string $id) : ? Job {
    $query = $this->connection->select('apigee_edge_job', 'j')
      ->fields('j', ['job']);
    $query->condition('id', $id);
    $jobdata = $query->execute()->fetchField();

    return $jobdata ? unserialize($jobdata) : NULL;
  }

  /**
   * Claims a job if one is available.
   *
   * @param null|string $tag
   *   Optional tag to filter with.
   *
   * @return \Drupal\apigee_edge\Job|null
   *   Job object or null if there is no available.
   */
  public function select(?string $tag = NULL) : ? Job {
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
      /** @var \Drupal\apigee_edge\Job $job */
      $job = unserialize($jobdata);
      $this->ensure($job, Job::SELECTED);

      return $job;
    }

    return NULL;
  }

  /**
   * Executes a job synchronously.
   *
   * @param \Drupal\apigee_edge\Job $job
   *   Job to run.
   * @param bool $update
   *   Whether to save the job into the database after it ran.
   *   Setting this to false means that it is the caller's responsibility to
   *   save the job into the database, else the job will be stuck in the
   *   "running" state.
   *
   * @throws \Exception
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
   * Executes a job asynchronously.
   *
   * This puts the job into the "apigee_edge_job" cron queue.
   *
   * @param \Drupal\apigee_edge\Job $job
   *   The job to execute later.
   *
   * @throws \Exception
   */
  public function cast(Job $job) {
    $this->save($job);
    $this->queue->createItem(['tag' => $job->getTag()]);
  }

  /**
   * Counts jobs in the queue.
   *
   * @param null|string $tag
   *   Optional tag to filter with.
   * @param array|null $statuses
   *   Optional statues to filter with.
   *
   * @return int
   *   Number of counted jobs.
   */
  public function countJobs(?string $tag = NULL, ?array $statuses = NULL) : int {
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
