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

use Drupal\apigee_edge\JobExecutor;

/**
 * A trait for all jobs that create other jobs.
 */
trait JobCreatorTrait {

  /**
   * Returns the job executor service.
   *
   * @return \Drupal\apigee_edge\JobExecutor
   *   The job executor service.
   */
  protected function getExecutor(): JobExecutor {
    return \Drupal::service('apigee_edge.job_executor');
  }

  /**
   * Schedules a job for execution.
   *
   * @param \Drupal\apigee_edge\Job\Job $job
   *   The job shluld be schedules.
   */
  protected function scheduleJob(Job $job) {
    $this->getExecutor()->save($job);
  }

  /**
   * Schedules multiple jobs for execution.
   *
   * @param \Drupal\apigee_edge\Job\Job[] $jobs
   *   The array of the jobs should be scheduled.
   */
  protected function scheduleJobs(array $jobs) {
    $executor = $this->getExecutor();
    foreach ($jobs as $job) {
      $executor->save($job);
    }
  }

}
