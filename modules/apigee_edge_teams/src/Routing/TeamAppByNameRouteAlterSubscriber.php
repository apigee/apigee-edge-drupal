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

namespace Drupal\apigee_edge_teams\Routing;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Changes the 'type' of the 'team_app' route parameter if 'team' is available.
 *
 * The {team_app} parameter has already automatically resolved by
 * EntityResolverManager and it is expected to contain a Team app id (UUID)
 * because this is what entity load can accept. Although, we wanted to generate
 * more user friendly paths for Team apps that does not yet have a _separated_
 * "Team apps" page, similar to "Apps" page for developer apps. Therefore we
 * added the team id (team name) to some regular team app entity routes (ex.:
 * canonical, add/edit/delete-form, etc.) and set the {team_app} parameter's
 * value to team app's name instead of its id (UUID).
 *
 * @see \Drupal\apigee_edge_teams\Entity\TeamApp::urlRouteParameters()
 */
final class TeamAppByNameRouteAlterSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($collection as $id => $route) {
      if (strpos($id, 'entity.team_app') !== FALSE && in_array('team', $route->compile()->getPathVariables()) && in_array('app', $route->compile()->getPathVariables())) {
        $params = $route->getOption('parameters') ?? [];
        NestedArray::setValue($params, ['team', 'type'], 'entity:team');
        NestedArray::setValue($params, ['app', 'type'], 'team_app_by_name');
        $route->setOption('parameters', $params);
      }
    }
  }

}
