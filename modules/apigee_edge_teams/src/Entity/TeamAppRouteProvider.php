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

use Drupal\apigee_edge\Entity\AppRouteProvider;
use Drupal\apigee_edge\Entity\AppTitleProvider;
use Drupal\apigee_edge_teams\Entity\ListBuilder\TeamAppListByTeam;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\Routing\Route;

/**
 * Default entity routes for team apps.
 */
class TeamAppRouteProvider extends AppRouteProvider {

  use TeamRoutingHelperTrait;

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);
    $entity_type_id = $entity_type->id();

    /** @var \Symfony\Component\Routing\Route $route */
    foreach ($collection as $route) {
      $this->alterRoutesWithAppName($route);
    }

    if ($add_form_for_team = $this->getAddFormRouteForTeam($entity_type)) {
      $collection->add("entity.{$entity_type_id}.add_form_for_team", $add_form_for_team);
    }

    if ($collection_by_team = $this->getCollectionRouteByTeam($entity_type)) {
      $collection->add("entity.{$entity_type_id}.collection_by_team", $collection_by_team);
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCollectionRoute($entity_type);
    if ($route) {
      $requirements = $route->getRequirements();
      // Users with "Manage Team Apps" permission should also have access.
      $permission = TeamAppPermissionProvider::MANAGE_TEAM_APPS_PERMISSION;
      if (isset($requirements['_permission'])) {
        $requirements['_permission'] .= "+{$permission}";
      }
      else {
        $requirements['_permission'] = $permission;
      }
      $route->setRequirements($requirements);
    }

    return $route;
  }

  /**
   * Gets the add-form route for team.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getAddFormRouteForTeam(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('add-form-for-team')) {
      $route = new Route($entity_type->getLinkTemplate('add-form-for-team'));
      $route->setDefault('_entity_form', 'team_app.add_for_team');
      $route->setDefault('_title_callback', AppTitleProvider::class . '::addTitle');
      $route->setDefault('entity_type_id', $entity_type->id());
      $this->ensureTeamParameter($route);
      $route->setRequirement('_entity_create_access', $entity_type->id());
      return $route;
    }
  }

  /**
   * Gets the collection route for a team.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getCollectionRouteByTeam(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('collection-by-team')) {
      $route = new Route($entity_type->getLinkTemplate('collection-by-team'));
      $route->setDefault('_controller', TeamAppListByTeam::class . '::render');
      $route->setDefault('_title_callback', TeamAppListByTeam::class . '::pageTitle');
      $this->ensureTeamParameter($route);
      $route->setRequirement('_apigee_edge_teams_team_app_list_by_team_access', 'TRUE');
      return $route;
    }
  }

  /**
   * Alters routers with {app} and not {team_app}.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   Route object.
   */
  private function alterRoutesWithAppName(Route $route): void {
    if (strpos($route->getPath(), '{app}') !== FALSE) {
      // "team_app" parameter must be removed otherwise it cause
      // MissingMandatoryParametersException exceptions.
      $options = $route->getOptions();
      unset($options['parameters']['team_app']);
      $route->setOptions($options);

      // Default access check must be replaced.
      // @see \Drupal\apigee_edge\Access\AppAccessCheckByAppName
      $requirements = $route->getRequirements();
      list(, $operation) = explode('.', $requirements['_entity_access']);
      $requirements['_app_access_check_by_app_name'] = $operation;
      unset($requirements['_entity_access']);
      $route->setRequirements($requirements);
    }
  }

}
