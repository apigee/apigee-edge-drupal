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
use Drupal\apigee_edge\Entity\Controller\OrganizationController;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\apigee_edge_teams\Entity\Form\TeamForm;
use Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface;
use Drupal\Component\Serialization\Json;

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

    foreach ($membership->getMembers() as $developer_id => $roles) {
      $roles = is_array($roles) ? $roles : [];

      /** @var \Drupal\user\Entity\User $account */
      $account = user_load_by_mail($developer_id);

      if (!$account) {
        /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamInvitationStorageInterface $teamInvitationStorage */
        $teamInvitationStorage = \Drupal::entityTypeManager()->getStorage('team_invitation');
        $teamInvitationStorage->create([
          'team' => ['target_id' => $this->appGroup],
          'team_roles' => array_values(array_map(function (string $role) {
            return ['target_id' => $role];
          }, $roles)),
          'recipient' => $developer_id,
        ])->save();
      }
      else {
        /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface|null $team_entity */
        $team_entity = \Drupal::entityTypeManager()->getStorage('team')->load($this->appGroup);
        /** @var \Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorageInterface $team_member_role_storage */
        $team_member_role_storage = \Drupal::entityTypeManager()->getStorage('team_member_role');
        $team_member_role_storage->addTeamRoles($account, $team_entity, $roles);
      }
    }
    $this->appGroupMembershipObjectCache->saveMembership($this->appGroup, $membership);

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
    // Get the existing members and roles from AppGroup ApigeeX.
    $apigeeReservedMembers = $this->decorated()->getReservedMembership();
    // Extract the attributes from response and get the __apigee_reserved__developer_details values.
    $attributeKey = $apigeeReservedMembers->getValue(TeamForm::APPGROUP_ADMIN_EMAIL_ATTRIBUTE);
    // If __apigee_reserved__developer_details attribute is not set.
    $existing_members = $attributeKey !== NULL ? Json::decode($attributeKey) : [];

    $new_membership = [];
    foreach ($members->getMembers() as $key => $value) {
      $new_membership['developer'] = $key;
      $new_membership['roles'] = $value;
    }
    array_push($existing_members, $new_membership);

    $unique_members;
    foreach ($existing_members as $key => $values) {
      $unique_members[$values['developer']] = $values;
    }
    // Adding the new members into the attribute.
    $apigeeReservedMembers->add(TeamForm::APPGROUP_ADMIN_EMAIL_ATTRIBUTE, Json::encode(array_values($unique_members)));

    // Storing membership info on appgroups __apigee_reserved__developer_details attribute for ApigeeX.
    $result = $this->decorated()->setReservedMembership($apigeeReservedMembers);

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
    // Get the existing members and roles from AppGroup ApigeeX.
    $apigeeReservedMembers = $this->decorated()->getReservedMembership();
    // Extract the attributes from response and get the __apigee_reserved__developer_details values.
    $attributeKey = $apigeeReservedMembers->getValue(TeamForm::APPGROUP_ADMIN_EMAIL_ATTRIBUTE);
    // If __apigee_reserved__developer_details attribute is not set.
    $existing_members = $attributeKey !== NULL ? Json::decode($attributeKey) : [];

    $unique_members = [];
    foreach ($existing_members as $key => $values) {
      if ($values['developer'] !== $email) {
        $unique_members[$values['developer']] = $values;
      }
    }
    // Adding the new members into the attribute.
    $apigeeReservedMembers->add(TeamForm::APPGROUP_ADMIN_EMAIL_ATTRIBUTE, Json::encode(array_values($unique_members)));
    // Storing membership info on appgroups __apigee_reserved__developer_details attribute for ApigeeX.
    // Removing and updating the member on appgroups __apigee_reserved__developer_details attribute for ApigeeX.
    $result = $this->decorated()->setReservedMembership($apigeeReservedMembers);
    $this->appGroupMembershipObjectCache->removeMembership($this->appGroup);
  }

}
