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

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\Routing\Route;

/**
 * Developer- and company (team) app specific route overrides and additions.
 */
class AppRouteProvider extends EdgeEntityRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);
    $entity_type_id = $entity_type->id();

    if ($analytics_route = $this->getAnalyticsRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.analytics", $analytics_route);
    }

    return $collection;
  }

  /**
   * Gets the analytics route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getAnalyticsRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('analytics') && $entity_type->hasHandlerClass('form', 'analytics')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('analytics'));
      $route->setDefault('_form', $entity_type->getFormClass('analytics'));
      $route->setDefault('_title_callback', AppTitleProvider::class . '::analyticsTitle');
      $route->setDefault('entity_type_id', $entity_type_id);
      $route->setRequirement('_entity_access', "{$entity_type_id}.analytics");
      // This is required because we are not using _entity_form.
      $route->setOption('parameters', [
        $entity_type_id => ['type' => 'entity:' . $entity_type_id],
      ]);

      return $route;
    }
  }

}
