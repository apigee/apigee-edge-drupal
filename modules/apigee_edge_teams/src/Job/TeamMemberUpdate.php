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

namespace Drupal\apigee_edge_teams\Job;

/**
 * A job to update team members.
 */
class TeamMemberUpdate extends TeamMemberCreateUpdate {

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Updating team (@team_name) members in Drupal.', [
      '@team_name' => $this->team_ids,
    ])->render();
  }

}
