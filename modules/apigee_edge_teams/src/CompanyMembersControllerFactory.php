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

use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Factory service that builds a company members controller.
 *
 * @internal You should use the team membership manager service instead of this.
 */
final class CompanyMembersControllerFactory implements CompanyMembersControllerFactoryInterface {

  /**
   * Internal cache for created instances.
   *
   * @var \Drupal\apigee_edge_teams\CompanyMembersControllerInterface[]
   */
  private $instances = [];

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $connector;

  /**
   * The company membership object cache.
   *
   * @var \Drupal\apigee_edge_teams\CompanyMembershipObjectCacheInterface
   */
  private $companyMembershipObjectCache;

  /**
   * CompanyMembersControllerFactory constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge_teams\CompanyMembershipObjectCacheInterface $company_membership_object_cache
   *   The company membership object cache.
   */
  public function __construct(SDKConnectorInterface $connector, CompanyMembershipObjectCacheInterface $company_membership_object_cache) {
    $this->connector = $connector;
    $this->companyMembershipObjectCache = $company_membership_object_cache;
  }

  /**
   * {@inheritdoc}
   */
  public function companyMembersController(string $company): CompanyMembersControllerInterface {
    if (!isset($this->instances[$company])) {
      $this->instances[$company] = new CompanyMembersController($company, $this->connector, $this->companyMembershipObjectCache);
    }

    return $this->instances[$company];
  }

}
