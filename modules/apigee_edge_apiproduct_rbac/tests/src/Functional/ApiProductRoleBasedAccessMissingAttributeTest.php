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
 * Validates "Show API products with missing or empty attribute to everyone"
 * settings.
 *
 * @group apigee_edge_apiproduct_rbac
 * @group apigee_edge
 * @group apigee_edge_access
 * @group apigee_edge_api_product
 * @group apigee_edge_api_product_access
 */
class ApiProductRoleBasedAccessMissingAttributeTest extends ApiProductRoleBasedAccessTestBase {

  /**
   * {@inheritdoc}
   *
   * \Drupal\Tests\apigee_edge\Functional\ApiProductAccessTest validates
   * developerAppEditFormTest().
   */
  public function testApiProductAccess() {
    $this->entityAccessTest();
  }

  /**
   * Tests entity access with empty/missing attributes.
   */
  protected function entityAccessTest() {
    // Some utility functions that we are going to use here.
    $checkRoles = function (callable $checkViewAccess, callable $checkAssignAccess, string $messageSuffix) {
      foreach (self::SUPPORTED_OPERATIONS as $operation) {
        foreach ([AccountInterface::ANONYMOUS_ROLE, AccountInterface::AUTHENTICATED_ROLE] as $role) {
          if ('assign' === $operation) {
            $checkAssignAccess($operation, $role, $messageSuffix);
          }
          else {
            $checkViewAccess($operation, $role, $messageSuffix);
          }
        }
      }
    };
    $shouldNotHaveAccess = function (string $operation, string $role, string $messageSuffix) {
      $this->assertFalse($this->apiProducts[self::PUBLIC_VISIBILITY]->access($operation, $this->users[$role]), "\"{$role}\" user should not had \"{$operation}\" access when {$messageSuffix}.");
    };
    $shouldHaveAccess = function (string $operation, string $role, string $messageSuffix) {
      $this->assertTrue($this->apiProducts[self::PUBLIC_VISIBILITY]->access($operation, $this->users[$role]), "\"{$role}\" user should had \"{$operation}\" access when {$messageSuffix}.");
    };

    // Ensure default configuration.
    $this->config('apigee_edge_apiproduct_rbac.settings')->set('grant_access_if_attribute_missing', FALSE)->save();
    $this->accessControlHandler->resetCache();
    // It should not have, but just to make it sure.
    if ($this->apiProducts[self::PUBLIC_VISIBILITY]->hasAttribute($this->rbacAttributeName)) {
      $this->apiProducts[self::PUBLIC_VISIBILITY]->deleteAttribute($this->rbacAttributeName);
    }
    // No attribute.
    $checkRoles($shouldNotHaveAccess, $shouldNotHaveAccess, 'attribute did not exist');
    // Empty attribute value.
    $this->apiProducts[self::PUBLIC_VISIBILITY]->setAttribute($this->rbacAttributeName, '');
    $checkRoles($shouldNotHaveAccess, $shouldNotHaveAccess, 'attribute value was empty');

    $this->config('apigee_edge_apiproduct_rbac.settings')->set('grant_access_if_attribute_missing', TRUE)->save();
    $this->accessControlHandler->resetCache();
    // Empty attribute value.
    $checkRoles($shouldHaveAccess, $shouldNotHaveAccess, 'attribute value was empty');
    // No attribute.
    $this->apiProducts[self::PUBLIC_VISIBILITY]->deleteAttribute($this->rbacAttributeName);
    $checkRoles($shouldHaveAccess, $shouldNotHaveAccess, 'attribute did not exist');
    // Revert to the original configuration.
    $this->config('apigee_edge_apiproduct_rbac.settings')->set('grant_access_if_attribute_missing', FALSE)->save();
  }

}
