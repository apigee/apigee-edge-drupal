<?php

namespace Drupal\apigee_edge\Job;

use Drupal\apigee_edge\Job;
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
  protected function getExecutor() : JobExecutor {
    return \Drupal::service('apigee_edge.job_executor');
  }

  /**
   * Schedules a job for execution.
   *
   * @param \Drupal\apigee_edge\Job $job
   *   The job shluld be schedules.
   */
  protected function scheduleJob(Job $job) {
    $this->getExecutor()->save($job);
  }

  /**
   * Schedules multiple jobs for execution.
   *
   * @param \Drupal\apigee_edge\Job[] $jobs
   *   The array of the jobs should be scheduled.
   */
  protected function scheduleJobs(array $jobs) {
    $executor = $this->getExecutor();
    foreach ($jobs as $job) {
      $executor->save($job);
    }
  }

}
