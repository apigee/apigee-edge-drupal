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
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Team specific dynamic entity route provider.
 */
class TeamRouteProvider extends EdgeEntityRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCollectionRoute($entity_type);
    if ($route) {
      // Allows to easily display something else than the entity's plural
      // label on the team listing page, ex.: "Manage teams".
      $route->setDefault('_title_callback', 'apigee_edge_teams_team_listing_page_title');
    }

    return $route;
  }

}
