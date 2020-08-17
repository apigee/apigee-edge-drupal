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

namespace Drupal\apigee_edge_teams\Event;

/**
 * Defines a list of events.
 */
final class TeamInvitationEvents {

  /**
   * Name of the event fired after a team_invitation is created.
   */
  const CREATED = "apigee_edge_teams.team_invitation.created";

  /**
   * Name of event fired after a team_invitation is accepted.
   */
  const ACCEPTED = "apigee_edge_teams.team_invitation.accepted";

  /**
   * Name of the event fired after a team_invitation is declined.
   */
  const DECLINED = "apigee_edge_teams.team_invitation.declined";

  /**
   * Name of the event fired after a team_invitation is cancelled.
   */
  const CANCELLED = "apigee_edge_teams.team_invitation.cancelled";

  /**
   * Name of the event fired after a team_invitation is deleted.
   */
  const DELETED = "apigee_edge_teams.team_invitation.deleted";

}
