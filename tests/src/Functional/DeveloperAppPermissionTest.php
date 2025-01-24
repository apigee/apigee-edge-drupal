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

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Api\Management\Entity\App;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Developer app entity permission test.
 *
 * @group apigee_edge
 * @group apigee_edge_developer_app
 * @group apigee_edge_permissions
 */
class DeveloperAppPermissionTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * A user with this permission has access to all routes by this entity.
   */
  protected const ADMINISTER_PERMISSION = 'administer developer_app';

  /**
   * Provides data set for our permission tests.
   */
  protected const PERMISSION_MATRIX = [
    'create developer_app' => ['add-form-for-developer'],
    'delete any developer_app' => ['delete-form', 'delete-form-for-developer'],
    'delete own developer_app' => ['delete-form', 'delete-form-for-developer'],
    'update any developer_app' => ['edit-form', 'edit-form-for-developer'],
    'update own developer_app' => ['edit-form', 'edit-form-for-developer'],
    'view any developer_app' => [
      'canonical',
      'canonical-by-developer',
      'api-keys',
    ],
    'view own developer_app' => [
      'canonical',
      'canonical-by-developer',
      'collection-by-developer',
      'api-keys',
    ],
    'analytics any developer_app' => ['analytics', 'analytics-for-developer'],
    'analytics own developer_app' => ['analytics', 'analytics-for-developer'],
    'access developer_app overview' => ['collection'],
    'add_api_key own developer_app' => ['add-api-key-form'],
    'add_api_key any developer_app' => ['add-api-key-form'],
    // We leave this empty because we add entity links to this data set
    // later.
    self::ADMINISTER_PERMISSION => [],
  ];

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * My Drupal account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $myAccount;

  /**
   * Other Drupal account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $otherAccount;

  /**
   * My developer app.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $myDeveloperApp;

  /**
   * Other's developer app.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $otherDeveloperApp;

  /**
   * User roles.
   *
   * @var \Drupal\user\RoleInterface[]
   */
  protected $roles;

  /**
   * Developer app entity routes.
   *
   * @var string[]
   */
  protected $entityRoutes;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityType = $this->container->get('entity_type.manager')->getDefinition('developer_app');

    // Skip api key routes. These are tested separately.
    $links = array_filter($this->entityType->get('links'), function ($path) {
      return strpos($path, '{consumer_key}') === FALSE;
    });
    $this->entityRoutes = array_keys($links);

    $this->revokeDefaultAuthUserPermissions();

    $this->myAccount = $this->createAccount([]);
    $this->otherAccount = $this->createAccount([]);

    /** @var \Drupal\apigee_edge\Entity\Developer $myDeveloper */
    $myDeveloper = Developer::load($this->myAccount->getEmail());
    /** @var \Drupal\apigee_edge\Entity\Developer $otherDeveloper */
    $otherDeveloper = Developer::load($this->otherAccount->getEmail());

    $this->myDeveloperApp = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $myDeveloper->uuid(),
    ]);
    $this->myDeveloperApp->save();
    $this->myDeveloperApp->setOwner($this->myAccount);

    $this->otherDeveloperApp = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $otherDeveloper->uuid(),
    ]);
    $this->otherDeveloperApp->save();
    $this->otherDeveloperApp->setOwner($this->otherAccount);

    foreach (array_keys(static::PERMISSION_MATRIX) as $permission) {
      $this->roles[$permission] = $this->createRole([$permission]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    try {
      if ($this->otherAccount !== NULL) {
        $this->otherAccount->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    try {
      if ($this->myAccount !== NULL) {
        $this->myAccount->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    parent::tearDown();
  }

  /**
   * Tests pages and permissions.
   */
  public function testPermissions() {
    foreach (array_keys(static::PERMISSION_MATRIX) as $permission) {
      $this->assertPermission($permission);
    }
  }

  /**
   * Revokes extra permissions that are granted to authenticated user.
   *
   * These permissions are granted in apigee_edge_install(), and while they make
   * sense from an UX point of view, they make testing permissions more
   * difficult.
   */
  protected function revokeDefaultAuthUserPermissions() {
    $definition = $this->entityType;
    $roles = [RoleInterface::AUTHENTICATED_ID];
    $entities = Role::loadMultiple($roles);

    $user_permissions = [];
    foreach ($roles as $rid) {
      $user_permissions[$rid] = $entities[$rid]->getPermissions() ?: [];
    }
    $authenticated_user_permissions = array_filter($user_permissions[RoleInterface::AUTHENTICATED_ID], function ($perm) use ($definition) {
      return preg_match("/own {$definition->id()}$/", $perm);
    });
    $authenticated_user_permissions[] = "create {$definition->id()}";
    user_role_revoke_permissions(RoleInterface::AUTHENTICATED_ID, $authenticated_user_permissions);
  }

  /**
   * Asserts that an account with a given permission can or can't access pages.
   *
   * @param string $permission
   *   Name of the permission to test.
   */
  protected function assertPermission(string $permission) {
    if ($this->loggedInUser) {
      $this->drupalLogout();
    }

    $old_roles = $this->myAccount->getRoles(TRUE);
    foreach ($old_roles as $old_role) {
      $this->myAccount->removeRole($old_role);
    }
    $this->myAccount->addRole($this->roles[$permission]);

    // It is not necessary to save the developer associated with this user.
    $this->disableUserPresave();
    $this->myAccount->save();
    $this->enableUserPresave();

    $routesWithAccess = static::PERMISSION_MATRIX[$permission];
    // A user with this permission has access to all routes by this entity.
    if ($permission === static::ADMINISTER_PERMISSION) {
      $routesWithAccess = $this->entityRoutes;
    }

    foreach ($this->entityRoutes as $rel) {
      $myUrl = static::fixUrl((string) $this->myDeveloperApp->toUrl($rel)->toString());
      $otherUrl = static::fixUrl((string) $this->otherDeveloperApp->toUrl($rel)->toString());
      $shouldAccess = in_array($rel, $routesWithAccess);
      if (strpos($permission, ' any ') !== FALSE) {
        $this->visitPages($myUrl, $shouldAccess, $rel, $permission);
        $this->visitPages($otherUrl, $shouldAccess, $rel, $permission);
      }
      elseif (strpos($permission, ' own ') !== FALSE) {
        $this->visitPages($myUrl, $shouldAccess, $rel, $permission);
        $this->visitPages($otherUrl, FALSE, $rel, $permission);
      }
      else {
        $this->visitPages($myUrl, $shouldAccess, $rel, $permission);
        // Issue #285, a user should not have access to other user's create
        // (own) app form.
        $otherShouldAccess = $permission === 'create developer_app' ? FALSE : $shouldAccess;
        $this->visitPages($otherUrl, $otherShouldAccess, $rel, $permission);
      }
    }
  }

  /**
   * Visits pages as both "my" user and the other user.
   *
   * @param string $url
   *   URL of the page to visit.
   * @param bool $my_access
   *   True if there is access to the page.
   * @param string $rel
   *   Entity route.
   * @param string $permission
   *   Permission.
   */
  protected function visitPages(string $url, bool $my_access, string $rel, string $permission) {
    $this->drupalLogin($this->myAccount);
    $this->visitPage($url, $my_access, $rel, $permission);
    $this->drupalLogin($this->otherAccount);
    $this->visitPage($url, FALSE, $rel, $permission);
    $this->drupalLogout();
  }

  /**
   * Visits a single page.
   *
   * @param string $url
   *   URL of the page to visit.
   * @param bool $access
   *   True if there is access to the page.
   * @param string $rel
   *   Entity route.
   * @param string $permission
   *   Permission.
   */
  protected function visitPage(string $url, bool $access, string $rel, string $permission) {
    $this->drupalGet($url);
    $code = $this->getSession()->getStatusCode();
    $username = 'unknown';
    if ($this->loggedInUser->id() === $this->myAccount->id()) {
      $username = 'my user';
    }
    elseif ($this->loggedInUser->id() === $this->otherAccount->id()) {
      $username = 'other user';
    }
    $debug = "{$url} ({$rel}) with \"{$permission}\" as {$username}";
    if ($access) {
      $this->assertEquals(200, $code, "Couldn't access {$debug} when it should have. Got HTTP {$code}, expected HTTP 200.");
    }
    else {
      $this->assertEquals(403, $code, "Could access {$debug} when it should not have. Got HTTP {$code}, expected HTTP 403.");
    }
  }

}
