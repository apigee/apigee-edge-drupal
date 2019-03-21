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

use Drupal\apigee_edge_teams\TeamContextManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds the _apigee_team_route option to developer (user) routes.
 *
 * @see \Drupal\apigee_edge_teams\TeamContextManager::getCorrespondingRouteNameForEntity()
 */
final class TeamContextSwitcherRouteAlterSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($collection as $id => $route) {
      // Add a corresponding team route if the team route defines a
      // corresponding developer route.
      if (($developer_route_id = $route->getOption(TeamContextManagerInterface::DEVELOPER_ROUTE_OPTION_NAME)) && ($developer_route = $collection->get($developer_route_id)) && empty($developer_route->getOption(TeamContextManagerInterface::TEAM_ROUTE_OPTION_NAME))) {
        $developer_route->setOption(TeamContextManagerInterface::TEAM_ROUTE_OPTION_NAME, $id);
      }
    }
  }

}
