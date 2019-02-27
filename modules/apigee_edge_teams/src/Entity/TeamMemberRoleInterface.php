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

namespace Drupal\apigee_edge_teams\Entity;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Definition of an entity that stores team member's roles within a team.
 */
interface TeamMemberRoleInterface extends FieldableEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Returns the developer's user entity.
   *
   * @return \Drupal\user\UserInterface|null
   *   The developer's user entity or null if the entity is new and it has not
   *   been set yet.
   */
  public function getDeveloper(): ?UserInterface;

  /**
   * Returns the team entity.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamInterface|null
   *   The team entity or null if the entity is new and it has not been set
   *   yet.
   */
  public function getTeam(): ?TeamInterface;

  /**
   * Returns the team roles of the developer within the team.
   *
   * @return \Drupal\apigee_edge_teams\Entity\TeamRoleInterface[]
   *   Array of team roles or an empty array if the entity is new and it has
   *   not been set yet.
   */
  public function getTeamRoles(): array;

}
