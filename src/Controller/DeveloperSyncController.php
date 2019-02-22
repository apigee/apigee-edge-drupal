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

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Job\DeveloperSync;
use Drupal\apigee_edge\Job\Job;
use Drupal\apigee_edge\JobExecutorInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the developer synchronization-related pages.
 */
class DeveloperSyncController extends ControllerBase {

  /**
   * Job executor.
   *
   * @var \Drupal\apigee_edge\JobExecutor
   */
  protected $executor;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * DeveloperSyncController constructor.
   *
   * @param \Drupal\apigee_edge\JobExecutorInterface $executor
   *   The job executor service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(JobExecutorInterface $executor, MessengerInterface $messenger) {
    $this->executor = $executor;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apigee_edge.job_executor'),
      $container->get('messenger')
    );
  }

  /**
   * Generates a job tag.
   *
   * @param string $type
   *   Tag type.
   *
   * @return string
   *   Job tag.
   */
  protected static function generateTag(string $type): string {
    return "developer_sync_{$type}_" . user_password();
  }

  /**
   * Returns the developer sync filter.
   *
   * @return null|string
   *   Filter condition or null if not set.
   */
  protected static function getFilter(): ?string {
    return ((string) \Drupal::config('apigee_edge.sync')->get('filter')) ?: NULL;
  }

  /**
   * Handler for 'apigee_edge.developer_sync.schedule'.
   *
   * Runs a developer sync in the background.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   HTTP response doing a redirect.
   */
  public function schedule(Request $request): RedirectResponse {
    $destination = $request->query->get('destination');

    $job = new DeveloperSync(static::getFilter());
    $job->setTag($this->generateTag('background'));
    apigee_edge_get_executor()->cast($job);

    $this->messenger()->addStatus($this->t('Developer synchronization is scheduled.'));

    return new RedirectResponse($destination);
  }

  /**
   * Handler for 'apigee_edge.developer_sync.run'.
   *
   * Starts the developer sync batch process.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   HTTP response doing a redirect.
   */
  public function run(Request $request): RedirectResponse {
    $destination = $request->query->get('destination');
    $batch = static::getBatch();
    batch_set($batch);
    return batch_process($destination);
  }

  /**
   * Gets the batch array.
   *
   * @return array
   *   The batch array.
   */
  public static function getBatch(): array {
    $tag = static::generateTag('batch');

    return [
      'title' => t('Synchronizing developers'),
      'operations' => [
        [[static::class, 'batchGenerateJobs'], [$tag]],
        [[static::class, 'batchExecuteJobs'], [$tag]],
      ],
      'finished' => [static::class, 'batchFinished'],
    ];
  }

  /**
   * The first batch operation.
   *
   * This generates the developer-user sync jobs for the second operation.
   *
   * @param string $tag
   *   Job tag.
   * @param array $context
   *   Batch context.
   */
  public static function batchGenerateJobs(string $tag, array &$context) {
    $job = new DeveloperSync(static::getFilter());
    $job->setTag($tag);
    apigee_edge_get_executor()->call($job);

    $context['message'] = (string) $job;
    $context['finished'] = 1.0;
  }

  /**
   * The second batch operation.
   *
   * @param string $tag
   *   Job tag.
   * @param array $context
   *   Batch context.
   */
  public static function batchExecuteJobs(string $tag, array &$context) {
    if (!isset($context['sandbox'])) {
      $context['sandbox'] = [];
    }

    $executor = apigee_edge_get_executor();
    $job = $executor->select($tag);

    if ($job === NULL) {
      $context['finished'] = 1.0;
      return;
    }

    $executor->call($job);

    $context['message'] = (string) $job;
    $context['finished'] = $executor->countJobs($tag, [Job::FAILED, Job::FINISHED]) / $executor->countJobs($tag);
  }

  /**
   * Batch finish callback.
   */
  public static function batchFinished() {
    \Drupal::messenger()->addStatus(t('Apigee Edge developers are in sync with Drupal users.'));
  }

}
