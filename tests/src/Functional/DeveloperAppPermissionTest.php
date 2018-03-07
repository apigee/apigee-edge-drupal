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
use Drupal\user\RoleInterface;

/**
 * @group apigee_edge
 * @group apigee_edge_developer_app
 * @group apigee_edge_permissions
 */
class DeveloperAppPermissionTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'apigee_edge',
    'apigee_edge_test',
  ];

  /**
   * A user with this permission has access to all routes by this entity.
   */
  protected const ADMINISTER_PERMISSION = 'administer developer_app';

  /**
   * Provides data set for our permission tests.
   *
   * @see permissionProvider()
   */
  protected const PERMISSION_MATRIX = [
    'create developer_app' => ['add-form-for-developer'],
    'delete any developer_app' => ['delete-form', 'delete-form-for-developer'],
    'delete own developer_app' => ['delete-form', 'delete-form-for-developer'],
    'update any developer_app' => ['edit-form', 'edit-form-for-developer'],
    'update own developer_app' => ['edit-form', 'edit-form-for-developer'],
    'view any developer_app' => ['canonical', 'canonical-by-developer'],
    'view own developer_app' => [
      'canonical',
      'canonical-by-developer',
      'collection-by-developer',
    ],
    'access developer_app overview' => ['collection'],
    // We leave this empty because we add entity links to this data set
    // later.
    'administer developer_app' => [],
  ];

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $myAccount;

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $otherAccount;

  /**
   * @var \Drupal\apigee_edge\Entity\DeveloperApp
   */
  protected $myApp;

  /**
   * @var \Drupal\apigee_edge\Entity\DeveloperApp
   */
  protected $otherApp;

  /**
   * @var \Drupal\user\Entity\Role[]
   */
  protected $roles;

  /**
   * @var string[]
   */
  protected $entityRoutes;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->profile = 'standard';
    parent::setUp();

    $this->revokeExtraPermissions();

    $this->myAccount = $this->createAccount([]);
    $this->otherAccount = $this->createAccount([]);

    /** @var \Drupal\apigee_edge\Entity\Developer $myDeveloper */
    $myDeveloper = Developer::load($this->myAccount->getEmail());
    /** @var \Drupal\apigee_edge\Entity\Developer $otherDeveloper */
    $otherDeveloper = Developer::load($this->otherAccount->getEmail());

    $this->myApp = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $myDeveloper->uuid(),
    ]);
    $this->myApp->save();
    $this->myApp->setOwner($this->myAccount);

    $this->otherApp = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $otherDeveloper->uuid(),
    ]);
    $this->otherApp->save();
    $this->otherApp->setOwner($this->otherAccount);

    foreach (array_keys(static::PERMISSION_MATRIX) as $permission) {
      $this->roles[$permission] = $this->createRole([$permission]);
    }

    $definition = \Drupal::entityTypeManager()->getDefinition('developer_app');
    $this->entityRoutes = array_keys($definition->get('links'));
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $this->otherApp->delete();
    $this->myApp->delete();
    $this->otherAccount->delete();
    $this->myAccount->delete();

    parent::tearDown();
  }

  /**
   * Revokes extra permissions that are granted to authenticated user.
   *
   * These permissions are granted in apigee_edge_install(), and while they make
   * sense from an UX point of view, they make testing permissions more
   * difficult.
   */
  protected function revokeExtraPermissions() {
    $authenticated_user_permissions = [
      'view own developer_app',
      'create developer_app',
      'update own developer_app',
      'delete own developer_app',
    ];

    user_role_revoke_permissions(RoleInterface::AUTHENTICATED_ID, $authenticated_user_permissions);
  }

  /**
   * Returns the list of the permissions.
   *
   * @return array
   *   List of function arguments.
   */
  public function permissionProvider() {
    return array_map(function (string $permission): array {
      return [$permission];
    }, array_keys(static::PERMISSION_MATRIX));
  }

  /**
   * Asserts that an account with a given permission can or can't access pages.
   *
   * @param string $permission
   *   Name of the permission to test.
   *
   * @dataProvider permissionProvider
   */
  public function testPermission(string $permission) {
    if ($this->loggedInUser) {
      $this->drupalLogout();
    }

    $old_roles = $this->myAccount->getRoles(TRUE);
    foreach ($old_roles as $old_role) {
      $this->myAccount->removeRole($old_role);
    }
    $this->myAccount->addRole($this->roles[$permission]);
    $this->myAccount->save();

    $routesWithAccess = static::PERMISSION_MATRIX[$permission];
    // A user with this permission has access to all routes by this entity.
    if ($permission === static::ADMINISTER_PERMISSION) {
      $routesWithAccess = $this->entityRoutes;
    }

    foreach ($this->entityRoutes as $rel) {
      $myUrl = (string) $this->myApp->url($rel);
      $otherUrl = (string) $this->otherApp->url($rel);
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
        $this->visitPages($otherUrl, $shouldAccess, $rel, $permission);
      }
    }
  }

  /**
   * Visits pages as both "my" user and the other user.
   *
   * @param string $url
   * @param bool $myAccess
   * @param string $rel
   * @param string $permission
   */
  protected function visitPages(string $url, bool $myAccess, string $rel, string $permission) {
    $this->drupalLogin($this->myAccount);
    $this->visitPage($url, $myAccess, $rel, $permission);
    $this->drupalLogin($this->otherAccount);
    $this->visitPage($url, FALSE, $rel, $permission);
    $this->drupalLogout();
  }

  /**
   * Visits a single page.
   *
   * @param string $url
   * @param bool $access
   * @param string $rel
   * @param string $permission
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
      if ($code !== 200) {
        $this->fail(sprintf("Couldn't access {$debug} when it should have. Got HTTP %d, expected HTTP %d.", $code, 200));
      }
    }
    else {
      if ($code < 400) {
        $this->fail(sprintf("Could access {$debug} when it should not have. Got HTTP %d, expected HTTP %d.", $code, 403));
      }
      elseif ($code !== 403) {
        $this->fail(sprintf("Invalid HTTP code on {$debug}. Got HTTP %d, expected HTTP %d.", $code, 403));
      }
    }
  }

}
