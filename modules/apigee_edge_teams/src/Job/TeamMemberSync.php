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

namespace Drupal\apigee_edge_teams\Job;

use Drupal\apigee_edge\Job\EdgeJob;
use Drupal\apigee_edge\Job\JobCreatorTrait;

/**
 * A job that synchronizes team members in drupal.
 */
class TeamMemberSync extends EdgeJob {

  use JobCreatorTrait;

  /**
   * Filter regexp.
   *
   * @var string
   */
  protected $filter = NULL;

  /**
   * TeamMemberSync constructor.
   *
   * @param null|string $filter
   *   An optional regexp filter.
   */
  public function __construct(?string $filter) {
    parent::__construct();
    $this->filter = $filter;
  }

  /**
   * Executes the request itself.
   */
  protected function executeRequest() {}

  /**
   * {@inheritdoc}
   */
  public function execute(): bool {
    parent::execute();

    $team_ids = array_keys(\Drupal::entityTypeManager()->getStorage('team')->loadMultiple());

    foreach ($team_ids as $team_name) {
      $update_team_member_job = new TeamMemberUpdate($team_name);
      $update_team_member_job->setTag($this->getTag());
      $this->scheduleJob($update_team_member_job);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Synchronizing Team Members in Drupal.')->render();
  }

}
