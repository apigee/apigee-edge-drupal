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
use Drupal\Core\Url;

/**
 * Base definition of the team context manager service.
 */
interface TeamContextManagerInterface {

  /**
   * The name of the route option for developer.
   */
  const DEVELOPER_ROUTE_OPTION_NAME = '_apigee_developer_route';

  /**
   * The name of the route option for team.
   */
  const TEAM_ROUTE_OPTION_NAME = '_apigee_team_route';

  /**
   * Determines the current context from the route.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The current entity or NULL.
   */
  public function getCurrentContextEntity(): ?EntityInterface;

  /**
   * Returns the destination url for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The developer or team entity.
   *
   * @return \Drupal\Core\Url|null
   *   The destination URL.
   */
  public function getDestinationUrlForEntity(EntityInterface $entity): ?Url;

  /**
   * Gets the corresponding route name for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The developer or team entity.
   *
   * @return null|string
   *   The corresponding route name if one is detected.
   */
  public function getCorrespondingRouteNameForEntity(EntityInterface $entity): ?string;

}
