<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\apigee_edge_teams;

use Drupal\views\EntityViewsData;

/**
 * Provides views data for the team_invitation entity type.
 */
class TeamInvitationViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Set the filter for status field.
    $data['team_invitation']['status']['filter']['id'] = 'team_invitation_status';

    // Provide relationship to user table via mail.
    $data['team_invitation']['recipient']['relationship']['id'] = 'standard';
    $data['team_invitation']['recipient']['relationship']['base'] = 'users_field_data';
    $data['team_invitation']['recipient']['relationship']['base field'] = 'mail';
    $data['team_invitation']['recipient']['relationship']['title'] = $this->t('User');
    $data['team_invitation']['nid']['relationship']['label'] = $this->t('Links the recipient to a user.');

    return $data;
  }

}
