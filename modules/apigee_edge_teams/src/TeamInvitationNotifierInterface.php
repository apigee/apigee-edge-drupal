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

use Drupal\apigee_edge_teams\Entity\TeamInvitationInterface;

/**
 * Defines an interface for team_invitation notifiers.
 */
interface TeamInvitationNotifierInterface {

  /**
   * Sends notification for the provided team_invitation.
   *
   * @param \Drupal\apigee_edge_teams\Entity\TeamInvitationInterface $team_invitation
   *   The team_invitation entity.
   *
   * @return bool
   *   TRUE if notifications successfully sent. FALSE otherwise.
   */
  public function sendNotificationsFor(TeamInvitationInterface $team_invitation): bool;

}
