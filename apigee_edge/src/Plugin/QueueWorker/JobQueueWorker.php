<?php

namespace Drupal\apigee_edge\Plugin\QueueWorker;

use Drupal\apigee_edge\Job;
use Drupal\apigee_edge\JobExecutor;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Worker class for processing a queue item.
 *
 * @QueueWorker(
 *   id = "apigee_edge_job",
 *   title = "Apigee Edge job runner",
 * )
 */
class JobQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The job executor service.
   *
   * @var \Drupal\apigee_edge\JobExecutor
   */
  protected $executor;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, JobExecutor $executor) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->executor = $executor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\apigee_edge\JobExecutor $executor */
    $executor = $container->get('apigee_edge.job_executor');
    return new static($configuration, $plugin_id, $plugin_definition, $executor);
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $job = $this->executor->load($data['id']);
    if (!$job) {
      return;
    }

    if ($job->getStatus() !== Job::SELECTED) {
      return;
    }

    $this->executor->call($job, FALSE);

    $status = $job->getStatus();
    if ($status === Job::IDLE || $status === Job::RESCHEDULED) {
      $this->executor->cast($job);
    }
    else {
      $this->executor->save($job);
    }
  }

}
