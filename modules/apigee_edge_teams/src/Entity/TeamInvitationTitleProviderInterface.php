<?php

/**
 * Copyright 2020 Google Inc.
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

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides an interface for team_invitation title provider.
 */
interface TeamInvitationTitleProviderInterface {

  /**
   * Provides the accept title for a team_invitation.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityInterface $_entity
   *   (optional) An entity, passed in directly from the request attributes.
   *
   * @return string|null
   *   The title for the entity accept page.
   */
  public function acceptTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL);

  /**
   * Provides the decline title for a team_invitation.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityInterface $_entity
   *   (optional) An entity, passed in directly from the request attributes.
   *
   * @return string|null
   *   The title for the entity decline page.
   */
  public function declineTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL);

  /**
   * Provides the resend title for a team_invitation.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityInterface $_entity
   *   (optional) An entity, passed in directly from the request attributes.
   *
   * @return string|null
   *   The title for the entity resend page.
   */
  public function resendTitle(RouteMatchInterface $route_match, EntityInterface $_entity = NULL);

}
