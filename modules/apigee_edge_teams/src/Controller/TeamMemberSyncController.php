<?php

/**
 * Copyright 2022 Google Inc.
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

namespace Drupal\apigee_edge_teams\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\apigee_edge\Job\Job;
use Drupal\apigee_edge\JobExecutorInterface;
use Drupal\apigee_edge_teams\Job\TeamMemberSync;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the team member synchronization-related pages.
 */
class TeamMemberSyncController extends ControllerBase {

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
   * TeamMemberSyncController constructor.
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
    return "team_member_sync_{$type}_" . \Drupal::service('password_generator')->generate();
  }

  /**
   * Returns the team member sync filter.
   *
   * @return null|string
   *   Filter condition or null if not set.
   */
  protected static function getFilter(): ?string {
    return ((string) \Drupal::config('apigee_edge.sync')->get('filter')) ?: NULL;
  }

  /**
   * Handler for 'apigee_edge_teams.team_member.schedule'.
   *
   * Runs a team member sync in the background.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   HTTP response doing a redirect.
   */
  public function schedule(Request $request): RedirectResponse {
    $destination = $request->query->get('destination');

    $job = new TeamMemberSync(static::getFilter());
    $job->setTag($this->generateTag('background'));
    apigee_edge_get_executor()->cast($job);

    $this->messenger()->addStatus($this->t('Team Member synchronization is scheduled.'));

    return new RedirectResponse($destination);
  }

  /**
   * Handler for 'apigee_edge_teams.team_member.run'.
   *
   * Starts the team member sync batch process.
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
      'title' => t('Synchronizing Team Member'),
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
   * This generates the team member sync jobs for the second operation.
   *
   * @param string $tag
   *   Job tag.
   * @param array $context
   *   Batch context.
   */
  public static function batchGenerateJobs(string $tag, array &$context) {
    $job = new TeamMemberSync(static::getFilter());
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
    \Drupal::messenger()->addStatus(t('Team members are synced in Drupal'));
  }

}
