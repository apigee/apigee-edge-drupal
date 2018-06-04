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

use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\apigee_edge\Functional\ApiProductAccessTest;
use Drupal\user\UserInterface;

/**
 * Validates role based access control on API products.
 *
 * @group apigee_edge_apiproduct_rbac
 * @group apigee_edge
 * @group apigee_edge_access
 * @group apigee_edge_api_product
 * @group apigee_edge_api_product_access
 */
class ApiProductRoleBasedAccessTest extends ApiProductAccessTest {

  private const USER_WITH_ADMIN_PERM = 'user_with_admin_perm';

  /**
   * @var string*/
  private $rbacAttributeName;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'apigee_edge_apiproduct_rbac',
  ];

  /**
   * @inheritdoc
   */
  protected function setUp() {
    parent::setUp();

    $this->users[self::USER_WITH_ADMIN_PERM] = $this->createAccount(['administer apigee edge']);

    // Set built-in API product access control to "revoke all" mode to make sure
    // that it is actually disabled by this module.
    $this->config('apigee_edge.api_product_settings')
      ->set('access', [
        self::PUBLIC_VISIBILITY => [],
        self::PRIVATE_VISIBILITY => [],
        self::INTERNAL_VISIBILITY => [],
      ])
      ->save();

    $this->rbacAttributeName = $this->config('apigee_edge_apiproduct_rbac.settings')->get('attribute_name');
  }

  public function testEntityAccess() : void {
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
      $this->assertFalse($this->products[self::PUBLIC_VISIBILITY]->access($operation, $this->users[$role]), "\"{$role}\" user should not had \"{$operation}\" access when {$messageSuffix}.");
    };
    $shouldHaveAccess = function (string $operation, string $role, string $messageSuffix) {
      $this->assertTrue($this->products[self::PUBLIC_VISIBILITY]->access($operation, $this->users[$role]), "\"{$role}\" user should had \"{$operation}\" access when {$messageSuffix}.");
    };

    // Ensure default configuration.
    $this->config('apigee_edge_apiproduct_rbac.settings')->set('grant_access_if_attribute_missing', FALSE)->save();
    $this->accessControlHandler->resetCache();
    // It should not have, but just to make it sure.
    if ($this->products[self::PUBLIC_VISIBILITY]->hasAttribute($this->rbacAttributeName)) {
      $this->products[self::PUBLIC_VISIBILITY]->deleteAttribute($this->rbacAttributeName);
    }
    // No attribute.
    $checkRoles($shouldNotHaveAccess, $shouldNotHaveAccess, 'attribute did not exist');
    // Empty attribute value.
    $this->products[self::PUBLIC_VISIBILITY]->setAttribute($this->rbacAttributeName, '');
    $checkRoles($shouldNotHaveAccess, $shouldNotHaveAccess, 'attribute value was empty');

    $this->config('apigee_edge_apiproduct_rbac.settings')->set('grant_access_if_attribute_missing', TRUE)->save();
    $this->accessControlHandler->resetCache();
    // Empty attribute value.
    $checkRoles($shouldHaveAccess, $shouldHaveAccess, 'attribute value was empty');
    // No attribute.
    $this->products[self::PUBLIC_VISIBILITY]->deleteAttribute($this->rbacAttributeName);
    $checkRoles($shouldHaveAccess, $shouldHaveAccess, 'attribute did not exist');
    // Revert to the original configuration.
    $this->config('apigee_edge_apiproduct_rbac.settings')->set('grant_access_if_attribute_missing', FALSE)->save();
    parent::testEntityAccess();
  }

  /**
   * @inheritdoc
   */
  protected function saveAccessSettings(array $settings): void {
    $post = [];
    foreach (array_keys($this->roleStorage->loadMultiple()) as $rid) {
      foreach ($settings as $visibility => $roles) {
        if (in_array($rid, $roles)) {
          $post["rbac[{$rid}][{$this->products[$visibility]->id()}]"] = TRUE;
        }
        else {
          $post["rbac[{$rid}][{$this->products[$visibility]->id()}]"] = FALSE;
        }
      }
    }
    $this->drupalLogin($this->users[self::USER_WITH_ADMIN_PERM]);
    $this->drupalPostForm('/admin/config/apigee-edge/product-settings/access-control', $post, 'Save configuration');
    $this->drupalLogout();
  }

  /**
   * @inheritdoc
   */
  protected function getRolesWithAccess(ApiProductInterface $product): array {
    $value = $product->getAttributeValue($this->rbacAttributeName) ?? '';
    return explode(APIGEE_EDGE_APIPRODUCT_RBAC_ATTRIBUTE_VALUE_DELIMITER, $value);
  }

  /**
   * @inheritdoc
   */
  protected function messageIfUserShouldHaveAccessByRole(string $operation, UserInterface $user, string $user_rid, array $rids_with_access, ApiProductInterface $product): string {
    return sprintf('User with "%s" role should have "%s" access to this API Product. RBAC attribute value: "%s". Roles with access granted: %s.', $user_rid, $operation, $product->getAttributeValue($this->rbacAttributeName), implode(', ', $rids_with_access));
  }

  /**
   * @inheritdoc
   */
  protected function messageIfUserShouldNotHaveAccess(string $operation, UserInterface $user, string $user_rid, array $rids_with_access, ApiProductInterface $product): string {
    return sprintf('"%s" user without "Bypass API Product access control" permission should not have "%s" access to this API Product. RBAC attribute value: "%s". Roles with access granted: %s.', $user_rid, $operation, $product->getAttributeValue($this->rbacAttributeName), implode(', ', $rids_with_access));
  }

}
