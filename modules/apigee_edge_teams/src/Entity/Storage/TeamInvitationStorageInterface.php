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

namespace Drupal\apigee_edge_teams\Entity\Storage;

use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines an interface for team_invitation storage.
 */
interface TeamInvitationStorageInterface extends EntityStorageInterface {

  /**
   * Returns all team_invitation entities for the provided email and team id.
   *
   * @param string $email
   *   The email address.
   * @param string|null $team_id
   *   The team id.
   *
   * @return array
   *   An array of team_invitation entities for this email address.
   */
  public function loadByRecipient(string $email, ?string $team_id = NULL): array;

}
