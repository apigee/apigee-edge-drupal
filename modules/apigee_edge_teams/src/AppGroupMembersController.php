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
   * {@inheritdoc}
   */
  public function getMembers(): AppGroupMembership {
    $membership = $this->appGroupMembershipObjectCache->getMembership($this->appGroup);
    if ($membership === NULL) {
      $membership = $this->decorated()->getMembers();
      $this->appGroupMembershipObjectCache->saveMembership($this->appGroup, $membership);
    }

    return $membership;
  }

  /**
   * {@inheritdoc}
   */
  public function setMembers(AppGroupMembership $members): AppGroupMembership {
    // Get the existing members and roles from AppGroup ApigeeX.
    $apigeeReservedMembers = $this->decorated()->getReservedMembership();
    // Extract the attributes from response and get the _apigee_reserve_membership values.
    $attributeKey = $apigeeReservedMembers->getValue(TeamForm::APPGROUP_ADMIN_EMAIL_ATTRIBUTE);
    $existing_members = Json::decode($attributeKey);

    $new_membership = [];
    foreach($members->getMembers() as $key => $value) {
        $new_membership['developer'] = $key; 
        $new_membership['roles'] = $value; 
    }
    array_push($existing_members, $new_membership);

    $unique_members;
    foreach($existing_members as $key => $values) {
      $unique_members[$values['developer']] = $values;
    }
    // Adding the new members into the attribute.
    $apigeeReservedMembers->add(TeamForm::APPGROUP_ADMIN_EMAIL_ATTRIBUTE, Json::encode(array_values($unique_members)));

    // Storing membership info on appgroups _apigee_reserved__memberships attribute for ApigeeX.
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
    // Extract the attributes from response and get the _apigee_reserve_membership values.
    $attributeKey = $apigeeReservedMembers->getValue(TeamForm::APPGROUP_ADMIN_EMAIL_ATTRIBUTE);
    $existing_members = Json::decode($attributeKey);

    $unique_members;
    foreach($existing_members as $key => $values) {
      if ($values['developer'] !== $email) {
        $unique_members[$values['developer']] = $values;
      }
    }
    // Adding the new members into the attribute.
    $apigeeReservedMembers->add(TeamForm::APPGROUP_ADMIN_EMAIL_ATTRIBUTE, Json::encode(array_values($unique_members)));
    // Storing membership info on appgroups _apigee_reserved__memberships attribute for ApigeeX.
    // Removing and updating the member on appgroups _apigee_reserved__memberships attribute for ApigeeX.
    $result = $this->decorated()->setReservedMembership($apigeeReservedMembers);
    $this->appGroupMembershipObjectCache->removeMembership($this->appGroup);
  }
}
