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

namespace Drupal\Tests\apigee_edge_teams\Functional;

use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;
use Drupal\Core\Url;

/**
 * Teams module access test.
 *
 * @group apigee_edge
 * @group apigee_edge_teams
 *
 * TODO Optimize code where it is possible.
 */
class AccessTest extends ApigeeEdgeTeamsFunctionalTestBase {

  /**
   * The team entity storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamStorageInterface
   */
  protected $teamStorage;

  /**
   * The team app entity storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamAppStorageInterface
   */
  protected $teamAppStorage;

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * Default user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  protected $teamMembershipManager;

  /**
   * Team entity to test.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamInterface
   */
  protected $team;

  /**
   * Team app entity to test.
   *
   * @var \Drupal\apigee_edge_teams\Entity\TeamAppInterface
   */
  protected $teamApp;

  /**
   * Team entity routes.
   *
   * Keyed by route id.
   *
   * @var \Symfony\Component\Routing\Route[]
   */
  protected $teamEntityRoutes = [];

  /**
   * Team app entity routes.
   *
   * Keyed by route id.
   *
   * @var \Symfony\Component\Routing\Route[]
   */
  protected $teamAppEntityRoutes = [];

  /**
   * Administer routes defined by the teams module.
   */
  protected const ADMIN_ROUTES = [
    'apigee_edge_teams.settings.team',
    'apigee_edge_teams.settings.team_app',
  ];

  /**
   * Team entity permission matrix.
   *
   * Key are site-wide permissions and values are routes without
   * entity.{entity_id}. that a user should have access with the permission.
   */
  protected const TEAM_PERMISSION_MATRIX = [
    'view any team' => ['canonical'],
    'create team' => ['add_form'],
    'update any team' => ['edit_form'],
    'delete any team' => ['delete_form'],
    // TODO Add test coverage for 'member.edit', 'member.remove' routes
    // by using a developer parameter in the route which belongs to a member of
    // the team and which belongs to a non-member of the team and by using an
    // email address of a non-existing developer.
    'manage team members' => ['members', 'add_members'],
  ];

  /**
   * Team membership level permission matrix.
   *
   * Key are team-level permissions and values are routes without
   * entity.{entity_id}. that a user should have access with the permission.
   */
  protected const TEAM_MEMBER_PERMISSION_MATRIX = [
    'team_manage_members' => [
      'members',
      'add_members',
      'member.edit',
      'member.remove',
    ],
    'team_app_create' => ['add_form_for_team'],
    'team_app_update' => ['edit_form'],
    'team_app_delete' => ['delete_form'],
    'team_app_analytics' => ['analytics'],
  ];

  /**
   * User roles associated to permissions.
   *
   * @var \Drupal\user\RoleInterface[]
   */
  protected $roles;

  /**
   * The team role storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamRoleStorageInterface
   */
  protected $teamRoleStorage;

  /**
   * The team permission handler.
   *
   * @var \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface
   */
  protected $teamPermissionHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->accountSwitcher = $this->container->get('account_switcher');
    $this->teamStorage = $this->container->get('entity_type.manager')->getStorage('team');
    $this->teamAppStorage = $this->container->get('entity_type.manager')->getStorage('team_app');
    $this->teamRoleStorage = $this->container->get('entity_type.manager')->getStorage('team_role');
    $this->teamMembershipManager = $this->container->get('apigee_edge_teams.team_membership_manager');
    $this->teamPermissionHandler = $this->container->get('apigee_edge_teams.team_permissions');

    $team_entity_type = $this->container->get('entity_type.manager')->getDefinition('team');
    $team_app_entity_type = $this->container->get('entity_type.manager')->getDefinition('team_app');
    /** @var \Drupal\Core\Entity\Routing\EntityRouteProviderInterface $provider */
    foreach ($this->container->get('entity_type.manager')->getRouteProviders('team') as $provider) {
      foreach ($provider->getRoutes($team_entity_type) as $id => $route) {
        $this->teamEntityRoutes[$id] = $route;
      }
    }
    /** @var \Drupal\Core\Entity\Routing\EntityRouteProviderInterface $provider */
    foreach ($this->container->get('entity_type.manager')->getRouteProviders('team_app') as $provider) {
      foreach ($provider->getRoutes($team_app_entity_type) as $id => $route) {
        $this->teamAppEntityRoutes[$id] = $route;
      }
    }

    $this->team = $this->teamStorage->create([
      'name' => strtolower($this->getRandomGenerator()->name()),
    ]);
    $this->team->save();

    $this->teamApp = $this->teamAppStorage->create([
      'name' => strtolower($this->getRandomGenerator()->name()),
      'companyName' => $this->team->getName(),
    ]);
    $this->teamApp->save();
    $this->account = $this->createAccount();

    // Create roles for team permissions.
    foreach (array_keys(static::TEAM_PERMISSION_MATRIX) as $permission) {
      $this->roles[$permission] = $this->createRole([$permission], preg_replace('/[^a-z0-9_]+/', '_', $permission));
    }

    // Create roles for admin permissions.
    $this->roles['administer apigee edge'] = $this->createRole(['administer apigee edge'], 'administer_apigee_edge');
    $this->roles['administer team'] = $this->createRole(['administer team'], 'administer_team');
    $this->roles['manage team apps'] = $this->createRole(['manage team apps'], 'manage_team_apps');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    if ($this->team !== NULL) {
      try {
        $this->teamStorage->delete([$this->team]);
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }

    if ($this->account !== NULL) {
      try {
        $this->account->delete();
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }

    parent::tearDown();
  }

  /**
   * Tests team, team membership level and admin permissions.
   */
  public function testAccess() {
    // Anonymous user has no access to team, team app and admin pages.
    $this->validateTeamAccess();
    $this->validateTeamAppAccess();
    $this->validateAccessToAdminRoutes(FALSE);

    // The user is not a member of the team and it has no teams related
    // permission. It has no access to view any team or team app related page.
    $this->drupalLogin($this->account);
    $this->validateTeamAccess();
    $this->validateTeamAppAccess();

    // The user is not a member of the team. Check every team entity related
    // permission one by one.
    foreach (array_keys(self::TEAM_PERMISSION_MATRIX) as $permission) {
      $this->setUserPermissions([$permission]);
      $this->validateTeamAccess();
      $this->validateTeamAppAccess();
    }

    // The user is not a member of the team but it has every team related
    // permission. It has no access to view any team app page.
    $this->setUserPermissions(array_keys(self::TEAM_PERMISSION_MATRIX));
    $this->validateTeamAccess();
    $this->validateTeamAppAccess();

    // The user is a member of the team but it has no team related permission
    // and every team member operation is disabled.
    $this->setUserPermissions([]);
    $this->setMemberPermissions([]);
    $this->teamMembershipManager->addMembers($this->team->getName(), [$this->account->getEmail()]);
    $this->validateTeamAccess();
    $this->validateTeamAppAccess();

    // The user is a member of the team. Check every team member level
    // permission one by one.
    foreach (array_keys(self::TEAM_MEMBER_PERMISSION_MATRIX) as $permission) {
      $this->setMemberPermissions([$permission]);
      $this->validateTeamAccess();
      $this->validateTeamAppAccess();
    }

    // The user is not a member of the team but every team member operation is
    // enabled. The user has no access to the team and team app related pages.
    $this->setMemberPermissions(array_keys(self::TEAM_MEMBER_PERMISSION_MATRIX));
    $this->teamMembershipManager->removeMembers($this->team->getName(), [$this->account->getEmail()]);
    $this->validateTeamAccess();
    $this->validateTeamAppAccess();

    // With administer apigee edge permission the user has no access to team,
    // team app and admin pages.
    $this->setUserPermissions(['administer apigee edge']);
    $this->validateTeamAccess();
    $this->validateTeamAppAccess();
    $this->validateAccessToAdminRoutes(FALSE);

    // With manage team apps permission the user has access to team app pages.
    $this->setUserPermissions(['manage team apps']);
    $this->validateTeamAccess();
    $this->validateTeamAppAccess(TRUE);
    $this->validateAccessToAdminRoutes(FALSE);

    // With administer teams permission the user has access to team, team app
    // and admin pages.
    $this->setUserPermissions(['administer team']);
    // Account must be member of the member to be able to access to member.edit
    // and member.remove routes.
    // TODO Use a different developer's email address who is member of the
    // team in these routes for testing.
    $this->teamMembershipManager->addMembers($this->team->getName(), [$this->account->getEmail()]);
    $this->validateTeamAccess(TRUE);
    $this->teamMembershipManager->addMembers($this->team->getName(), [$this->account->getEmail()]);
    $this->validateTeamAppAccess(TRUE);
    $this->validateAccessToAdminRoutes(TRUE);
  }

  /**
   * Checks whether the user has access to team pages.
   *
   * @param bool $admin_access
   *   TRUE if the user has access to every team page.
   */
  protected function validateTeamAccess(bool $admin_access = FALSE) {
    $route_ids_with_access = [];

    if ($admin_access) {
      $route_ids_with_access = array_map(function (string $route_id) {
        return str_replace('entity.team.', '', $route_id);
      }, array_keys($this->teamEntityRoutes));
    }
    else {
      if ($this->drupalUserIsLoggedIn($this->account)) {
        foreach (array_keys(self::TEAM_PERMISSION_MATRIX) as $permission) {
          if ($this->account->hasPermission($permission)) {
            $route_ids_with_access = array_merge($route_ids_with_access, self::TEAM_PERMISSION_MATRIX[$permission]);
          }
        }

        // Authenticated users always have access to team collection.
        $route_ids_with_access[] = 'collection';

        // Team members always have access to the team canonical page.
        if (in_array($this->account->getEmail(), $this->teamMembershipManager->getMembers($this->team->getName()))) {
          $route_ids_with_access[] = 'canonical';
          if (in_array('team_manage_members', $this->teamPermissionHandler->getDeveloperPermissionsByTeam($this->team, $this->account))) {
            $route_ids_with_access = array_merge($route_ids_with_access, self::TEAM_MEMBER_PERMISSION_MATRIX['team_manage_members']);
          }
        }
      }
    }

    foreach ($this->teamEntityRoutes as $route_id => $route) {
      $short_route_id = str_replace('entity.team.', '', $route_id);
      $rel = str_replace('_', '-', $short_route_id);
      // First try to use the entity to generate the url - and with that
      // make sure the url parameter resolver works on the entity.
      if ($this->team->hasLinkTemplate($rel)) {
        $url = $this->team->toUrl($rel);
      }
      else {
        // If the route is not registered as link in entity links - because
        // it contains a route parameter that the entity can not resolve -
        // fallback to the URL resolver.
        $params = ['team' => $this->team->id()];
        if (strpos($route->getPath(), '{developer}') !== FALSE) {
          $params['developer'] = $this->account->getEmail();
        }
        $url = Url::fromRoute($route_id, $params);
      }

      if (in_array($short_route_id, $route_ids_with_access)) {
        $this->validateAccess($url, TRUE);
      }
      else {
        $this->validateAccess($url, FALSE);
      }
    }
  }

  /**
   * Checks whether the user has access to team app pages.
   *
   * @param bool $admin_access
   *   TRUE if the user has access to every team app page.
   */
  protected function validateTeamAppAccess(bool $admin_access = FALSE) {
    $route_ids_with_access = [];

    if ($admin_access) {
      $route_ids_with_access = array_map(function (string $route_id) {
        return str_replace('entity.team_app.', '', $route_id);
      }, array_keys($this->teamAppEntityRoutes));
    }
    else {
      if ($this->drupalUserIsLoggedIn($this->account)) {
        if (in_array($this->account->getEmail(), $this->teamMembershipManager->getMembers($this->team->getName()))) {
          $route_ids_with_access = [
            'collection_by_team',
            'canonical',
          ];

          foreach (array_keys(self::TEAM_MEMBER_PERMISSION_MATRIX) as $permission) {
            if (in_array($permission, $this->teamPermissionHandler->getDeveloperPermissionsByTeam($this->team, $this->account))) {
              $route_ids_with_access = array_merge($route_ids_with_access, self::TEAM_MEMBER_PERMISSION_MATRIX[$permission]);
            }
          }
        }
      }
    }

    foreach ($this->teamAppEntityRoutes as $route_id => $route) {
      $short_route_id = str_replace('entity.team_app.', '', $route_id);
      $rel = str_replace('_', '-', $short_route_id);
      // First try to use the entity to generate the url - and with that
      // make sure the url parameter resolver works on the entity.
      if ($this->teamApp->hasLinkTemplate($rel)) {
        $url = $this->teamApp->toUrl($rel);
      }
      else {
        // If the route is not registered as link in entity links - because
        // it contains a route parameter that the entity can not resolve -
        // fallback to the URL resolver.
        $params = ['team' => $this->team->id()];
        if (strpos($route->getPath(), '{app}') !== FALSE) {
          $params['app'] = $this->teamApp->getName();
        }
        $url = Url::fromRoute($route_id, $params);
      }

      if (in_array($short_route_id, $route_ids_with_access)) {
        $this->validateAccess($url, TRUE);
      }
      else {
        $this->validateAccess($url, FALSE);
      }
    }
  }

  /**
   * Checks whether the user has access to the given URL.
   *
   * @param \Drupal\Core\Url $url
   *   The Url object to check.
   * @param bool $user_has_access
   *   TRUE if the user has access to the given URL, else FALSE.
   */
  protected function validateAccess(Url $url, bool $user_has_access) {
    $this->drupalGet($url->toString());
    $code = $this->getSession()->getStatusCode();
    $current_user_roles = implode(', ', $this->account->getRoles());

    if ($user_has_access) {
      $this->assertEquals(200, $code, "Has no access to {$url->getInternalPath()}. User roles: {$current_user_roles}");
    }
    else {
      $this->assertEquals(403, $code, "Has access to {$url->getInternalPath()}. User roles: {$current_user_roles}");
    }
  }

  /**
   * Checks whether the user has access to admin routes.
   *
   * @param bool $user_has_access
   *   TRUE if the user has access to admin routes, else FALSE.
   */
  protected function validateAccessToAdminRoutes(bool $user_has_access) {
    foreach (self::ADMIN_ROUTES as $route_name) {
      $this->validateAccess(Url::fromRoute($route_name), $user_has_access);
    }
  }

  /**
   * Sets team permissions.
   *
   * @param array $permissions
   *   Array of team permissions to give.
   */
  protected function setUserPermissions(array $permissions) {
    $old_roles = $this->account->getRoles(TRUE);
    foreach ($old_roles as $old_role) {
      $this->account->removeRole($old_role);
    }

    foreach ($permissions as $permission) {
      $this->account->addRole($this->roles[$permission]);
    }

    // It is not necessary to save the developer associated with this user.
    $this->disableUserPresave();
    $this->account->save();
    $this->enableUserPresave();
  }

  /**
   * Sets team membership level permissions.
   *
   * @param array $permissions
   *   Team membership level permissions to enable.
   */
  protected function setMemberPermissions(array $permissions) {
    $permission_changes = [];
    foreach (array_keys(self::TEAM_MEMBER_PERMISSION_MATRIX) as $permission) {
      $permission_changes[$permission] = in_array($permission, $permissions);
    }

    $this->teamRoleStorage->changePermissions(TeamRoleInterface::TEAM_MEMBER_ROLE, $permission_changes);
  }

}
