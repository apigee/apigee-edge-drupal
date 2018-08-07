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

namespace Drupal\Tests\apigee_edge_apiproduct_rbac\Functional;

use Drupal\Core\Session\AccountInterface;

/**
 * Validates role based access control on API products.
 *
 * Validates entity access with authenticated and internal roles.
 *
 * @group apigee_edge_apiproduct_rbac
 * @group apigee_edge
 * @group apigee_edge_access
 * @group apigee_edge_api_product
 * @group apigee_edge_api_product_access
 */
class ApiProductRoleBasedAccessAuthenticatedInternalTest extends ApiProductRoleBasedAccessTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->ridCombinations = $this->calculateRidCombinations([AccountInterface::ANONYMOUS_ROLE, AccountInterface::AUTHENTICATED_ROLE]);
  }

}
