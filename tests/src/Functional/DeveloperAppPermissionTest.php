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

/**
 * @group apigee_edge
 */
class DeveloperAppPermissionTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'apigee_edge',
  ];

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
    'administer developer_app' => [
      'canonical',
      'collection',
      'add-form',
      'edit-form',
      'delete-form',
      'canonical-by-developer',
      'collection-by-developer',
      'add-form-for-developer',
      'edit-form-for-developer',
      'delete-form-for-developer',
    ],
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
  protected $pagelist;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->profile = 'standard';
    parent::setUp();

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
    $this->pagelist = array_keys($definition->get('links'));
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
   * Tests the permission matrix.
   */
  public function testPermissions() {
    foreach (array_keys(static::PERMISSION_MATRIX) as $permission) {
      $this->assertAccount($permission);
    }
  }

  /**
   * Asserts that an account with a given permission can or can't access pages.
   *
   * @param string $permission
   */
  protected function assertAccount(string $permission) {
    if ($this->loggedInUser) {
      $this->drupalLogout();
    }

    $old_roles = $this->myAccount->getRoles(TRUE);
    foreach ($old_roles as $old_role) {
      $this->myAccount->removeRole($old_role);
    }
    $this->myAccount->addRole($this->roles[$permission]);
    $this->myAccount->save();

    foreach ($this->pagelist as $rel) {
      $myUrl = (string) $this->myApp->url($rel);
      $otherUrl = (string) $this->otherApp->url($rel);
      $shouldAccess = in_array($rel, static::PERMISSION_MATRIX[$permission]);
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
    $debug = "{$url} ({$rel}) as {$username} with \"{$permission}\"";
    if ($access) {
      $this->assertEquals(200, $code, "Can access {$debug}");
    }
    else {
      $this->assertEquals(403, $code, "Can't access {$debug}");
    }
  }

}
