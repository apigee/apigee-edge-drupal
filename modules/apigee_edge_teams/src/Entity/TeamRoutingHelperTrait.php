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

use Symfony\Component\Routing\Route;

/**
 * Contains utility methods for team and team app routes.
 */
trait TeamRoutingHelperTrait {

  /**
   * If route contains the {team} parameter add required changes to the route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to be checked and altered if needed.
   */
  private function ensureTeamParameter(Route $route) {
    if (strpos($route->getPath(), '{team}') !== FALSE) {
      // Make sure the parameter gets up-casted.
      // (This also ensures that we get an "Page not found" page if user with
      // uid does not exist.)
      $route->setOption('parameters', ['team' => ['type' => 'entity:team', 'converter' => 'paramconverter.entity']]);
    }
  }

}
