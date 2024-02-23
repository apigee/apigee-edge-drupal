<?php

/**
 * Copyright 2022 Google Inc.
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

namespace Drupal\apigee_edge_teams\Job;

use Drupal\apigee_edge\Job\EdgeJob;

/**
 * Base class for team member sync jobs.
 */
abstract class TeamMemberCreateUpdate extends EdgeJob {

  /**
   * Team ids of the organization.
   *
   * @var string
   */
  protected $team_ids;

  /**
   * TeamMemberCreateUpdate constructor.
   *
   * @param string $team_ids
   *   The team ids of the organization.
   */
  public function __construct(string $team_ids) {
    parent::__construct();
    $this->team_ids = $team_ids;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    $orgController = \Drupal::service('apigee_edge.controller.organization');
    $member_controller = \Drupal::service('apigee_edge_teams.team_membership_manager');

    if ($orgController->isOrganizationApigeeX()) {
      $team_members = $member_controller->syncAppGroupMembers($this->team_ids);
    }
    else {
      $team_members = $member_controller->getMembers($this->team_ids);
    }
  }

}
