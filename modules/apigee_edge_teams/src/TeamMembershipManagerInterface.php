<?php

/**
 * Copyright 2018 Google Inc.
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

/**
 * Base definition of the team membership manager service.
 *
 * This service make easier to retrieve and update company (team) and developer
 * membership information. Hides the complexity of the company members API
 * that we did not hide in the SDK.
 */
interface TeamMembershipManagerInterface {

  /**
   * Returns members of a team.
   *
   * @param string $team
   *   Name of a team.
   *
   * @return string[]
   *   Array of developer email addresses.
   */
  public function getMembers(string $team): array;

  /**
   * Adds members to a team.
   *
   * @param string $team
   *   Name of a team.
   * @param array $developers
   *   Array of developer email addresses.
   */
  public function addMembers(string $team, array $developers): void;

  /**
   * Removes members from a team.
   *
   * @param string $team
   *   Name of a team.
   * @param array $developers
   *   Array of developer email addresses.
   */
  public function removeMembers(string $team, array $developers): void;

  /**
   * Returns the list of teams where the developer is currently a member.
   *
   * @param string $developer
   *   Developer email address.
   *
   * @return string[]
   *   Array of team names.
   *
   * @throws \Drupal\apigee_edge\Exception\DeveloperDoesNotExistException
   *   If developer not found with id.
   */
  public function getTeams(string $developer): array;

}
