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

namespace Drupal\apigee_edge;

use Drupal\apigee_edge\Job\Job;

/**
 * Job executor service definition.
 */
interface JobExecutorInterface {

  /**
   * Saves a job.
   *
   * @param \Drupal\apigee_edge\Job\Job $job
   *   Job object.
   *
   * @throws \Exception
   */
  public function save(Job $job);

  /**
   * Loads a job from the database.
   *
   * @param string $id
   *   Job id.
   *
   * @return \Drupal\apigee_edge\Job\Job|null
   *   Loaded job object or null if it does not exit.
   */
  public function load(string $id): ?Job;

  /**
   * Claims a job if one is available.
   *
   * @param null|string $tag
   *   Optional tag to filter with.
   *
   * @return \Drupal\apigee_edge\Job\Job|null
   *   Job object or null if there is no available.
   */
  public function select(?string $tag = NULL): ?Job;

  /**
   * Executes a job synchronously.
   *
   * @param \Drupal\apigee_edge\Job\Job $job
   *   Job to run.
   * @param bool $update
   *   Whether to save the job into the database after it ran.
   *   Setting this to false means that it is the caller's responsibility to
   *   save the job into the database, else the job will be stuck in the
   *   "running" state.
   *
   * @throws \Exception
   */
  public function call(Job $job, bool $update = TRUE);

  /**
   * Executes a job asynchronously.
   *
   * This puts the job into the "apigee_edge_job" cron queue.
   *
   * @param \Drupal\apigee_edge\Job\Job $job
   *   The job to execute later.
   *
   * @throws \Exception
   */
  public function cast(Job $job);

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
  public function countJobs(?string $tag = NULL, ?array $statuses = NULL): int;

}
