<?php

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
      while ($this->executeJob());

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
