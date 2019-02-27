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

namespace Drupal\apigee_edge\Plugin\QueueWorker;

use Drupal\apigee_edge\Job\Job;
use Drupal\apigee_edge\JobExecutorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
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
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, JobExecutorInterface $executor, QueueFactory $queue_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->executor = $executor;
    $this->queue = $queue_factory->get('apigee_edge_job');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\apigee_edge\JobExecutor $executor */
    $executor = $container->get('apigee_edge.job_executor');
    /** @var \Drupal\Core\Queue\QueueFactory $queueFactory */
    $queue_factory = $container->get('queue');
    return new static($configuration, $plugin_id, $plugin_definition, $executor, $queue_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $job = $this->executor->select($data['tag']);

    if (!$job || $job->getStatus() !== Job::SELECTED) {
      return;
    }

    $this->executor->call($job);

    $this->queue->createItem(['tag' => $data['tag']]);
  }

}
