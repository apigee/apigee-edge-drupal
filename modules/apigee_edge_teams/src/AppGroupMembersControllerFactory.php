<?php

/**
 * Copyright 2023 Google Inc.
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

use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Factory service that builds a appgroup members controller.
 *
 * @internal You should use the team membership manager service instead of this.
 */
final class AppGroupMembersControllerFactory implements AppGroupMembersControllerFactoryInterface {

  /**
   * Internal cache for created instances.
   *
   * @var \Drupal\apigee_edge_teams\AppGroupMembersControllerInterface[]
   */
  private $instances = [];

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $connector;

  /**
   * The appgroup membership object cache.
   *
   * @var \Drupal\apigee_edge_teams\AppGroupMembershipObjectCacheInterface
   */
  private $appGroupMembershipObjectCache;

  /**
   * AppGroupMembersControllerFactory constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge_teams\AppGroupMembershipObjectCacheInterface $appgroup_membership_object_cache
   *   The appgroup membership object cache.
   */
  public function __construct(SDKConnectorInterface $connector, AppGroupMembershipObjectCacheInterface $appgroup_membership_object_cache) {
    $this->connector = $connector;
    $this->appGroupMembershipObjectCache = $appgroup_membership_object_cache;
  }

  /**
   * {@inheritdoc}
   */
  public function appGroupMembersController(string $appGroup): AppGroupMembersControllerInterface {
    if (!isset($this->instances[$appGroup])) {
      $this->instances[$appGroup] = new AppGroupMembersController($appGroup, $this->connector, $this->appGroupMembershipObjectCache);
    }

    return $this->instances[$appGroup];
  }

}
