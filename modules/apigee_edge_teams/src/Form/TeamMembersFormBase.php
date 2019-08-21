<?php

/*
 * Copyright 2019 Google Inc.
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

namespace Drupal\apigee_edge_teams\Form;

use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base team members form.
 */
abstract class TeamMembersFormBase extends FormBase {

  /**
   * The team from the route.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $team;

  /**
   * Team role storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamRoleStorageInterface
   */
  protected $teamRoleStorage;

  /**
   * Team member role storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorage
   */
  protected $teamMemberRoleStorage;

  /**
   * TeamMembersFormBase constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->teamRoleStorage = $entity_type_manager->getStorage('team_role');
    $this->teamMemberRoleStorage = $entity_type_manager->getStorage('team_member_role');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns an array of team role options keyed by team role id.
   *
   * @return array
   *   Role options.
   */
  protected function getRoleOptions() {
    $role_options = array_reduce($this->teamRoleStorage->loadMultiple(), function (array $carry, TeamRoleInterface $role) {
      $carry[$role->id()] = $role->label();
      return $carry;
    }, []);

    return $role_options;
  }

  /**
   * Helper function to filter the value of a team roles checkboxes element.
   *
   * It will only leave selected items, and also remove the TEAM_MEMBER_ROLE,
   * as it is granted implicitly.
   *
   * @param array $team_roles_value
   *   The checkboxes element values.
   *
   * @return array
   *   The filtered result.
   */
  protected function filterSelectedRoles(array $team_roles_value) {
    $selected_roles = array_filter($team_roles_value, function ($role) {
      return $role && $role != TeamRoleInterface::TEAM_MEMBER_ROLE;
    });

    return $selected_roles;
  }

}
