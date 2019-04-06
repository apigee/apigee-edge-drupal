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

namespace Drupal\apigee_edge_teams;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * Describes the `apigee_edge_teams.context_manager` service.
 *
 * This service is responsible for understanding the context of the current
 * route. Context will either be developer context or team context.
 */
class TeamContextManager implements TeamContextManagerInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * TeamContextManager constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentContextEntity(): ?EntityInterface {
    $context = NULL;
    if ($current_route_object = $this->routeMatch->getRouteObject()) {
      // Check for developer/user route.
      if ($current_route_object->hasOption(static::TEAM_ROUTE_OPTION_NAME)) {
        $context = $this->routeMatch->getParameter('user');
      }

      // Check for team route.
      if ($current_route_object->hasOption(static::DEVELOPER_ROUTE_OPTION_NAME)) {
        $context = $this->routeMatch->getParameter('team');
      }
    }

    return $context instanceof EntityInterface ? $context : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDestinationUrlForEntity(EntityInterface $entity): ?Url {
    if ($corresponding_route_name = $this->getCorrespondingRouteNameForEntity($entity)) {
      // Rebuild parameters for current context.
      $parameters = array_diff_key($this->routeMatch->getRawParameters()->all(), [$entity->getEntityTypeId() === 'user' ? 'team' : 'user' => NULL]);
      $parameters[$entity->getEntityTypeId()] = $entity->id();

      return Url::fromRoute($corresponding_route_name, $parameters);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCorrespondingRouteNameForEntity(EntityInterface $entity): ?string {
    if ($current_route_object = $this->routeMatch->getRouteObject()) {
      // If the route has same parameter type as entity, return current route.
      if ($this->routeMatch->getRawParameters()->has($entity->getEntityTypeId())) {
        return $this->routeMatch->getRouteName();
      }

      // Otherwise return the corresponding route if set.
      return $current_route_object->getOption(static::DEVELOPER_ROUTE_OPTION_NAME) ?? $current_route_object->getOption(static::TEAM_ROUTE_OPTION_NAME);
    }

    return NULL;
  }

}
