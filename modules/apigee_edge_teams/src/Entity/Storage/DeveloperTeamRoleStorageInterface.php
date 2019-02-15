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

namespace Drupal\apigee_edge_teams\Entity\Storage;

use Drupal\apigee_edge_teams\Entity\DeveloperTeamRoleInterface;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an interface for developer team role entity storage classes.
 */
interface DeveloperTeamRoleStorageInterface extends ContentEntityStorageInterface {

  /**
   * Load developer team role object by developer and team.
   *
   * WARNING: The fact whether the developer is actually member of the team
   * (company) in Apigee Edge is not being verified here. The caller should
   * perform this check if needed. Thanks for this approach we can minimize
   * the API calls that are being sent to Apigee Edge.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User entity object of a developer.
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   Team entity object.
   *
   * @return \Drupal\apigee_edge_teams\Entity\DeveloperTeamRoleInterface|null
   *   Developer team role object if the developer has team roles within a team,
   *   null otherwise.
   */
  public function loadByDeveloperAndTeam(AccountInterface $account, TeamInterface $team): ?DeveloperTeamRoleInterface;

  /**
   * Loads all team roles of a developer within all its teams.
   *
   * WARNING: The fact whether the developer is actually member of the team
   * (company) in Apigee Edge is not being verified here. The caller should
   * perform this check if needed. Thanks for this approach we can minimize
   * the API calls that are being sent to Apigee Edge.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User entity object of a developer.
   *
   * @return \Drupal\apigee_edge_teams\Entity\DeveloperTeamRoleInterface[]
   *   Array of developer team role object.
   */
  public function loadByDeveloper(AccountInterface $account): array;

  /**
   * Loads all team roles of all team members within a team.
   *
   * WARNING: The fact whether the developer is actually member of the team
   * (company) in Apigee Edge is not being verified here. The caller should
   * perform this check if needed. Thanks for this approach we can minimize
   * the API calls that are being sent to Apigee Edge.
   *
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   Team entity object.
   *
   * @return \Drupal\apigee_edge_teams\Entity\DeveloperTeamRoleInterface[]
   *   Array of developer team role objects related to a team.
   */
  public function loadByTeam(TeamInterface $team): array;

  /**
   * Adds team roles to a developer in a team.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User entity object of a developer.
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   Team entity object.
   * @param string[] $roles
   *   Array of team role entity ids.
   *
   * @return \Drupal\apigee_edge_teams\Entity\DeveloperTeamRoleInterface
   *   The updated developer team role entity.
   *
   * @throws \Drupal\apigee_edge_teams\Exception\InvalidArgumentException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addTeamRoles(AccountInterface $account, TeamInterface $team, array $roles): DeveloperTeamRoleInterface;

  /**
   * Removes team roles of a developer within a team.
   *
   * If you would like to remove a developer from a team (remove its "member"
   * team role) use the team membership manager service.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User entity object of a developer.
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   Team entity object.
   * @param string[] $roles
   *   Array of team role entity ids.
   *
   * @return \Drupal\apigee_edge_teams\Entity\DeveloperTeamRoleInterface
   *   The updated developer team role entity.
   */
  public function removeTeamRoles(AccountInterface $account, TeamInterface $team, array $roles): DeveloperTeamRoleInterface;

}
