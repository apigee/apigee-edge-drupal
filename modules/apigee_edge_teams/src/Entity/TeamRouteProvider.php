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

use Drupal\apigee_edge\Entity\EdgeEntityRouteProvider;
use Drupal\apigee_edge_teams\Controller\TeamMembersList;
use Drupal\apigee_edge_teams\Form\AddTeamMembersForm;
use Drupal\apigee_edge_teams\Form\EditTeamMemberForm;
use Drupal\apigee_edge_teams\Form\RemoveTeamMemberForm;
use Drupal\apigee_edge_teams\TeamContextManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\Routing\Route;

/**
 * Team specific dynamic entity route provider.
 */
class TeamRouteProvider extends EdgeEntityRouteProvider {

  use TeamRoutingHelperTrait;

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);
    $entity_type_id = $entity_type->id();

    if ($list_team_members = $this->getListTeamMembersRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.members", $list_team_members);
    }

    if ($add_team_members = $this->getAddTeamMembersRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.add_members", $add_team_members);
    }

    if ($edit_team_member = $this->getEditTeamMemberRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.member.edit", $edit_team_member);
    }

    if ($remove_team_member = $this->getRemoveTeamMemberRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.member.remove", $remove_team_member);
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCanonicalRoute($entity_type);

    // Set the corresponding developer route.
    $route->setOption(TeamContextManagerInterface::DEVELOPER_ROUTE_OPTION_NAME, 'entity.user.canonical');

    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCollectionRoute($entity_type);
    if ($route) {
      // Allows to easily display something else than the entity's plural
      // label on the team listing page, ex.: "Manage teams".
      $route->setDefault('_title_callback', 'apigee_edge_teams_team_listing_page_title');
      $requirements = $route->getRequirements();
      // We handle access to teams in the team list builder so "administer team"
      // default permission based restriction can be also removed.
      unset($requirements['_permission']);
      $requirements['_user_is_logged_in'] = 'TRUE';
      $route->setRequirements($requirements);
    }

    return $route;
  }

  /**
   * Gets the list team members route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getListTeamMembersRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('members')) {
      $route = new Route($entity_type->getLinkTemplate('members'));
      $route->setDefault('_controller', TeamMembersList::class . '::overview');
      $route->setDefault('_title_callback', TeamTitleProvider::class . '::teamMembersList');
      $route->setDefault('entity_type_id', $entity_type->id());
      $this->ensureTeamParameter($route);
      $route->setRequirement('_apigee_edge_teams_manage_team_access', 'TRUE');
      return $route;
    }

    return NULL;
  }

  /**
   * Gets the add team members route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getAddTeamMembersRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('add-members')) {
      $route = new Route($entity_type->getLinkTemplate('add-members'));
      $route->setDefault('_form', AddTeamMembersForm::class);
      $route->setDefault('_title', 'Invite members');
      $route->setDefault('entity_type_id', $entity_type->id());
      $this->ensureTeamParameter($route);
      $route->setRequirement('_apigee_edge_teams_manage_team_access', 'TRUE');
      return $route;
    }

    return NULL;
  }

  /**
   * Gets the edit team member route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getEditTeamMemberRoute(EntityTypeInterface $entity_type) {
    // Because this path depends on the {developer} route parameter that
    // can not be resolved in Team::urlRouteParameters() therefore this path
    // can not be defined in the link templates defined on the Team entity.
    $route = new Route('/teams/{team}/members/{developer}/edit');
    $route->setDefault('_form', EditTeamMemberForm::class);
    $route->setDefault('_title', 'Edit member');
    $route->setDefault('entity_type_id', $entity_type->id());
    $route->setRequirement('_apigee_edge_teams_manage_team_access', 'TRUE');
    // Make sure parameters gets up-casted.
    // (This also ensures that we get an "Page not found" page if user with
    // uid does not exist.)
    $route->setOption('parameters', [
      'team' => [
        'type' => 'entity:team',
        'converter' => 'paramconverter.entity',
      ],
      'developer' => [
        'converter' => 'paramconverter.developer_with_user',
      ],
    ]);
    return $route;
  }

  /**
   * Gets the remove team member route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRemoveTeamMemberRoute(EntityTypeInterface $entity_type) {
    // Because this path depends on the {developer} route parameter that
    // can not be resolved in Team::urlRouteParameters() therefore this path
    // can not be defined in the link templates defined on the Team entity.
    $route = new Route('/teams/{team}/members/{developer}/remove');
    $route->setDefault('_form', RemoveTeamMemberForm::class);
    $route->setDefault('_title', 'Remove member');
    $route->setDefault('entity_type_id', $entity_type->id());
    $route->setRequirement('_apigee_edge_teams_manage_team_access', 'TRUE');
    // Make sure parameters gets up-casted.
    // (This also ensures that we get an "Page not found" page if user with
    // uid does not exist.)
    $route->setOption('parameters', [
      'team' => [
        'type' => 'entity:team',
        'converter' => 'paramconverter.entity',
      ],
      'developer' => [
        'converter' => 'paramconverter.developer_with_user',
      ],
    ]);
    return $route;
  }

}
