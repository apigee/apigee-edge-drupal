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

namespace Drupal\apigee_edge_teams\Plugin\views\filter;

use Drupal\apigee_edge_teams\Entity\TeamInvitationInterface;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Providers filtering for team_invitation status.
 *
 * @ViewsFilter("team_invitation_status")
 */
class TeamInvitationStatusFilter extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    $this->valueOptions = [
      TeamInvitationInterface::STATUS_PENDING => $this->t('Pending'),
      TeamInvitationInterface::STATUS_ACCEPTED => $this->t('Accepted'),
      TeamInvitationInterface::STATUS_DECLINED => $this->t('Declined'),
      TeamInvitationInterface::STATUS_EXPIRED => $this->t('Expired'),
    ];

    return $this->valueOptions;
  }

}
