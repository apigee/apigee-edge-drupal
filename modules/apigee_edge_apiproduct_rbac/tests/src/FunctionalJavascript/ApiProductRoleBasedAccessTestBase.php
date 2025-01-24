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

namespace Drupal\Tests\apigee_edge_apiproduct_rbac\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\Tests\apigee_edge\FunctionalJavascript\ApiProductAccessTest;
use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\user\UserInterface;

/**
 * Base calls for validating role based access control on API products.
 *
 * Test suite had to be separated to smaller classes because the complete
 * permission matrix test took more than 50 minutes and Travis shut it down
 * every time.
 */
abstract class ApiProductRoleBasedAccessTestBase extends ApiProductAccessTest {

  protected const USER_WITH_ADMIN_PERM = 'user_with_admin_perm';

  /**
   * API product RBAC attribute name.
   *
   * @var string
   */
  protected $rbacAttributeName;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'apigee_edge_apiproduct_rbac_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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

  /**
   * {@inheritdoc}
   */
  protected function saveAccessSettings(array $settings) {
    $post = [];
    foreach (array_keys($this->roleStorage->loadMultiple()) as $rid) {
      foreach ($settings as $visibility => $roles) {
        if (in_array($rid, $roles)) {
          $post["rbac[{$rid}][{$this->apiProducts[$visibility]->id()}]"] = TRUE;
        }
        else {
          $post["rbac[{$rid}][{$this->apiProducts[$visibility]->id()}]"] = FALSE;
        }
      }
    }
    $this->drupalLogin($this->users[self::USER_WITH_ADMIN_PERM]);
    $this->drupalGet(Url::fromRoute('apigee_edge.settings.developer.api_product_access'));
    $this->submitForm($post, 'Save configuration');
    $this->drupalLogout();
  }

  /**
   * {@inheritdoc}
   */
  protected function getRolesWithAccess(ApiProductInterface $product): array {
    $value = $product->getAttributeValue($this->rbacAttributeName) ?? '';
    return explode(APIGEE_EDGE_APIPRODUCT_RBAC_ATTRIBUTE_VALUE_DELIMITER, $value);
  }

  /**
   * {@inheritdoc}
   */
  protected function messageIfUserShouldHaveAccessByRole(string $operation, UserInterface $user, string $user_rid, array $rids_with_access, ApiProductInterface $product): string {
    return sprintf('User with "%s" role should have "%s" access to this API Product. RBAC attribute value: "%s". Roles with access granted: %s.', $user_rid, $operation, $product->getAttributeValue($this->rbacAttributeName), implode(', ', $rids_with_access));
  }

  /**
   * {@inheritdoc}
   */
  protected function messageIfUserShouldNotHaveAccess(string $operation, UserInterface $user, string $user_rid, array $rids_with_access, ApiProductInterface $product): string {
    return sprintf('"%s" user without "Bypass API Product access control" permission should not have "%s" access to this API Product. RBAC attribute value: "%s". Roles with access granted: %s.', $user_rid, $operation, $product->getAttributeValue($this->rbacAttributeName), implode(', ', $rids_with_access));
  }

}
