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

use Drupal\apigee_edge\Job;

/**
 * A job that executes multiple jobs.
 */
class JobList extends Job {

  /**
   * List of jobs to execute.
   *
   * @var \Drupal\apigee_edge\Job[]
   */
  protected $jobs;

  /**
   * Current job position.
   *
   * @var int
   */
  protected $currentJob = 0;

  /**
   * Whether to execute all jobs at once.
   *
   * This is useful when multiple jobs are combed in a synchronous operation.
   *
   * @var bool
   */
  protected $executeAll;

  /**
   * {@inheritdoc}
   *
   * @param bool $execute_all
   *   Whether to try to execute all jobs in one run.
   */
  public function __construct($execute_all = FALSE) {
    parent::__construct();
    $this->executeAll = $execute_all;
  }

  /**
   * Adds a job to the job list.
   *
   * @param \Drupal\apigee_edge\Job $job
   *   The job should be added.
   */
  public function addJob(Job $job) {
    $this->jobs[] = $job;
  }

  /**
   * Executes a single job.
   *
   * @return bool
   *   The job's result.
   */
  protected function executeJob() : bool {
    if (isset($this->jobs[$this->currentJob])) {
      $result = $this->jobs[$this->currentJob]->execute();
      if (!$result) {
        $this->currentJob++;
      }

      return isset($this->jobs[$this->currentJob]);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() : bool {
    if ($this->executeAll) {
      // @codingStandardsIgnoreStart
      while ($this->executeJob());
      // @codingStandardsIgnoreEnd

      return FALSE;
    }

    return $this->executeJob();
  }

  /**
   * {@inheritdoc}
   */
  public function renderArray() : array {
    $render = [];
    foreach ($this->jobs as $job) {
      $render[] = $job->renderArray();
    }

    return $render;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() : string {
    $strings = [];

    foreach ($this->jobs as $job) {
      $strings[] = $job->__toString();
    }

    return implode(', ', $strings);
  }

  /**
   * Whether the job list is empty.
   *
   * @return bool
   *   TRUE if the job list is empty.
   */
  public function isEmpty() : bool {
    return count($this->jobs) === 0;
  }

}
