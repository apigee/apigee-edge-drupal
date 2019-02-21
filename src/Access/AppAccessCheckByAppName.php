<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Check access on app routes that contains {app} (app name) instead of app id.
 *
 * @see \Drupal\apigee_edge\Routing\DeveloperAppByNameRouteAlterSubscriber
 */
final class AppAccessCheckByAppName implements AccessInterface {

  /**
   * Checks access to an app entity operation on the given route.
   *
   * EntityAccessCheck only works if the route contains a parameter that
   * matches the name of the entity type. Ex.: {developer_app} and not {app}.
   *
   * @code
   * pattern: '/user/{user}/{app}'
   * requirements:
   *   _developer_app_access: 'view'
   * @endcode
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @see \Drupal\Core\Entity\EntityAccessCheck
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    $operation = $route->getRequirement('_app_access_check_by_app_name');
    // If $entity_type parameter is a valid entity, call its own access check.
    $parameters = $route_match->getParameters();
    /** @var \Drupal\apigee_edge\Entity\AppInterface $entity */
    $entity = $parameters->get('app');
    if ($entity) {
      return $entity->access($operation, $account, TRUE);
    }
    // No opinion, so other access checks should decide if access should be
    // allowed or not.
    return AccessResult::neutral();
  }

}
