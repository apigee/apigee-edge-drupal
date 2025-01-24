<?php

/**
 * Copyright 2023 Google Inc.
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

namespace Drupal\apigee_edge_teams\User;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Error;
use Drupal\apigee_edge\User\PostUserDeleteActionPerformerInterface;
use Drupal\user\UserInterface;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;

/**
 * Ensures team roles of the removed user also get deleted.
 */
final class RemoveTeamRolesOfUserSynchronousPostUserDeleteActionPerformer implements PostUserDeleteActionPerformerInterface {

  /**
   * Constructs a new object.
   */
  public function __construct(private readonly PostUserDeleteActionPerformerInterface $decorated, private readonly EntityTypeManagerInterface $entityTypeManager, private readonly LoggerInterface $logger) {}

  /**
   * {@inheritdoc}
   */
  public function __invoke(UserInterface $user): void {
    ($this->decorated)($user);

    /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorageInterface $team_member_role_storage */
    $team_member_role_storage = $this->entityTypeManager->getStorage('team_member_role');
    // When a user gets deleted then its developer account also gets deleted
    // from Apigee Edge which removes its (team) company memberships.
    // We must delete this user's team roles from Drupal as well.
    foreach ($team_member_role_storage->loadByDeveloper($user) as $team_member_roles_in_team) {
      try {
        $team_member_roles_in_team->delete();
      }
      catch (\Exception $e) {
        Error::logException($this->logger, $e, "Integrity check: Failed to remove %developer team member's roles in %team team when its Drupal user got deleted. Reason: @message", [
          '%developer' => $user->getEmail(),
          '%team' => $team_member_roles_in_team->getTeam()->id(),
        ], LogLevel::CRITICAL);
      }
    }
  }

}
