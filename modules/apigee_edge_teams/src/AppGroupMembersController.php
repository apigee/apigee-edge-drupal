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

namespace Drupal\apigee_edge_teams;

use Apigee\Edge\Api\ApigeeX\Controller\AppGroupMembersController as ApigeeXAppGroupMembersController;
use Apigee\Edge\Api\ApigeeX\Structure\AppGroupMembership;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\apigee_edge_teams\Entity\TeamInvitationInterface;
use Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface;

/**
 * Definition of the AppGroup members controller service.
 *
 * @internal You should use the team membership manager service instead of this.
 */
final class AppGroupMembersController implements AppGroupMembersControllerInterface {

  /**
   * Name of a appgroup.
   *
   * @var string
   */
  private $appGroup;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $connector;

  /**
   * The appgroup membership object cache.
   *
   * @var \Drupal\apigee_edge_teams\AppGroupMembershipObjectCacheInterface
   */
  private $appGroupMembershipObjectCache;

  /**
   * AppGroupMembersController constructor.
   *
   * @param string $appGroup
   *   The name of the appgroup.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge_teams\AppGroupMembershipObjectCacheInterface $appgroup_membership_object_cache
   *   The appgroup membership object cache.
   */
  public function __construct(string $appGroup, SDKConnectorInterface $connector, AppGroupMembershipObjectCacheInterface $appgroup_membership_object_cache) {
    $this->appGroup = $appGroup;
    $this->connector = $connector;
    $this->appGroupMembershipObjectCache = $appgroup_membership_object_cache;
  }

  /**
   * Returns the decorated appgroup members controller from the SDK.
   *
   * @return \Apigee\Edge\Api\ApigeeX\Controller\AppGroupMembersController
   *   The initialized appgroup members controller.
   */
  private function decorated(): ApigeeXAppGroupMembersController {
    return new ApigeeXAppGroupMembersController($this->appGroup, $this->connector->getOrganization(), $this->connector->getClient());
  }

  /**
   * {@inheritdoc}
   */
  public function getAppGroup(): string {
    return $this->decorated()->getAppGroup();
  }

  /**
   * Syncs the appgroup team members email and roles in database.
   *
   * @return \Apigee\Edge\Api\ApigeeX\Structure\AppGroupMembership
   *   Array of developers with their optional roles in the appgroup.
   */
  public function syncAppGroupMembers(): AppGroupMembership {
    $membership = $this->decorated()->getMembers();

    $developer_exist = TRUE;
    foreach ($membership->getMembers() as $developer_id => $roles) {
      $roles = is_array($roles) ? $roles : [];

      /** @var \Drupal\user\Entity\User $account */
      $account = user_load_by_mail($developer_id);

      if (!$account) {
        $developer_exist = FALSE;
        /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamInvitationStorageInterface $teamInvitationStorage */
        $teamInvitationStorage = \Drupal::entityTypeManager()->getStorage('team_invitation');

        $pending_invitations = array_filter($teamInvitationStorage->loadByRecipient($developer_id, $this->appGroup), function (TeamInvitationInterface $team_invitation) {
          return $team_invitation->isPending();
        });

        // Checking if this developer has already pending invite to prevent multiple invites.
        if (count($pending_invitations) === 0) {
          $teamInvitationStorage->create([
            'team' => ['target_id' => $this->appGroup],
            'team_roles' => array_values(array_map(function (string $role) {
              return ['target_id' => $role];
            }, $roles)),
            'recipient' => $developer_id,
          ])->save();
        }
      }
      else {
        /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface|null $team_entity */
        $team_entity = \Drupal::entityTypeManager()->getStorage('team')->load($this->appGroup);
        /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorageInterface $team_member_role_storage */
        $team_member_role_storage = \Drupal::entityTypeManager()->getStorage('team_member_role');
        $team_member_role_storage->addTeamRoles($account, $team_entity, $roles);
      }
    }
    // Caching the membership only if all the developer exists in Drupal
    // to avoid team member without access.
    if ($developer_exist) {
      $this->appGroupMembershipObjectCache->saveMembership($this->appGroup, $membership);
    }

    return $membership;
  }

  /**
   * {@inheritdoc}
   */
  public function getMembers(): AppGroupMembership {
    $membership = $this->appGroupMembershipObjectCache->getMembership($this->appGroup);
    if ($membership === NULL) {
      /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface|null $team_entity */
      $team_entity = \Drupal::entityTypeManager()->getStorage('team')->load($this->appGroup);
      // Load team_member_role object.
      $team_member_role_storage = \Drupal::entityTypeManager()->getStorage('team_member_role');
      $members = array_reduce($team_member_role_storage->loadByTeam($team_entity), function ($carry, TeamMemberRoleInterface $developer_role) {
          $carry[$developer_role->getDeveloper()->getEmail()] = $developer_role->getDeveloper()->getEmail();
          return $carry;
      },
      []);
      $membership = new AppGroupMembership($members);
      $this->appGroupMembershipObjectCache->saveMembership($team_entity->id(), $membership);
    }

    return $membership;
  }

  /**
   * {@inheritdoc}
   */
  public function setMembers(AppGroupMembership $members): AppGroupMembership {
    // Get the existing members and roles from ApigeeX.
    $apigeeReservedMembers = $this->decorated()->getMembers();
    // Getting all developers including the newly added one.
    $developers = array_merge($apigeeReservedMembers->getMembers(), $members->getMembers());

    foreach ($developers as $developer => $roles) {
      $members->setMember($developer, $roles);
    }
    // Storing membership info on appgroups __apigee_reserved__developer_details attribute for ApigeeX.
    $result = $this->decorated()->setMembers($members);

    // Returned membership does not contain all actual members of the appGroup,
    // so it is easier to remove the membership object from the cache and
    // enforce reload in getMembers().
    $this->appGroupMembershipObjectCache->removeMembership($this->appGroup);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function removeMember(string $email): void {
    // Get the existing members and roles from ApigeeX.
    $apigeeReservedMembers = $this->decorated()->getMembers();

    foreach ($apigeeReservedMembers->getMembers() as $developer => $roles) {
      if ($developer === $email) {
        $apigeeReservedMembers->removeMember($developer);
      }
    }
    // Storing membership info on appgroups __apigee_reserved__developer_details attribute for ApigeeX.
    // Removing and updating the member on appgroups __apigee_reserved__developer_details attribute for ApigeeX.
    $result = $this->decorated()->setMembers($apigeeReservedMembers);

    $this->appGroupMembershipObjectCache->removeMembership($this->appGroup);
  }

}
