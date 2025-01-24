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

use Drupal\Core\Url;
use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

/**
 * Teams module access test.
 *
 * @group apigee_edge
 * @group apigee_edge_teams
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
   * Default user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * Drupal user who is a member of the team.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $teamMemberAccount;

  /**
   * Drupal user who is not a member of the team.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $nonTeamMemberAccount;

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
    'manage team members' => [
      'members',
      'add_members',
      'member.edit',
      'member.remove',
    ],
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
    'team_app_view' => ['canonical', 'collection_by_team', 'api_keys'],
    'team_app_create' => ['add_form_for_team'],
    'team_app_update' => ['edit_form'],
    'team_app_delete' => ['delete_form'],
    'team_app_analytics' => ['analytics'],
    'team_app_add_api_key' => ['add_api_key_form'],
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
   * The team member role storage.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Storage\TeamMemberRoleStorageInterface
   */
  protected $teamMemberRoleStorage;

  /**
   * The team permission handler.
   *
   * @var \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface
   */
  protected $teamPermissionHandler;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'apigee_edge_teams_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->teamStorage = $this->container->get('entity_type.manager')->getStorage('team');
    $this->teamAppStorage = $this->container->get('entity_type.manager')->getStorage('team_app');
    $this->teamRoleStorage = $this->container->get('entity_type.manager')->getStorage('team_role');
    $this->teamMemberRoleStorage = $this->container->get('entity_type.manager')->getStorage('team_member_role');
    $this->teamMembershipManager = $this->container->get('apigee_edge_teams.team_membership_manager');
    $this->teamPermissionHandler = $this->container->get('apigee_edge_teams.team_permissions');
    $this->state = $this->container->get('state');

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

    // Skip api key routes. These are tested separately.
    $this->teamAppEntityRoutes = array_filter($this->teamAppEntityRoutes, function (Route $route) {
      return strpos($route->getPath(), '{consumer_key}') === FALSE;
    });

    $this->team = $this->teamStorage->create([
      'name' => mb_strtolower($this->getRandomGenerator()->name()),
    ]);
    $this->team->save();

    $this->teamApp = $this->teamAppStorage->create([
      'name' => mb_strtolower($this->getRandomGenerator()->name()),
      'companyName' => $this->team->getName(),
    ]);
    $this->teamApp->save();
    $this->account = $this->createAccount();

    $this->nonTeamMemberAccount = $this->createAccount();
    $this->teamMemberAccount = $this->createAccount();
    $this->teamMembershipManager->addMembers($this->team->getName(), [$this->teamMemberAccount->getEmail()]);

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
  protected function tearDown(): void {
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

    if ($this->teamMemberAccount !== NULL) {
      try {
        $this->teamMemberAccount->delete();
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }

    if ($this->nonTeamMemberAccount !== NULL) {
      try {
        $this->nonTeamMemberAccount->delete();
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }

    parent::tearDown();
  }

  /**
   * Tests team, team membership level and admin permissions, team roles.
   */
  public function testAccess() {
    $this->teamAccessTest();
    $this->teamRoleAccessTest();
    $this->teamExpansionTest();
  }

  /**
   * Tests team, team membership level and admin permissions.
   */
  protected function teamAccessTest() {
    // Ensure the current user is anonymous.
    if ($this->loggedInUser) {
      $this->drupalLogout();
    }
    // Anonymous user has no access to team, team app and admin pages.
    $this->validateTeamAccess();
    $this->validateTeamAppAccess();
    $this->validateAccessToAdminRoutes(FALSE);

    // The user is not a member of the team and it has no teams related
    // permission. It has no access to view any team or team app related page.
    $this->drupalLogin($this->account);
    $this->validateTeamAccess();
    $this->validateTeamAppAccess();

    // The user is not a member of the team. Grant every team entity related
    // permission one by one and validate available UIs.
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

    // The user is a member of the team but it has no team related site-wide
    // permission and every team permission is also revoked.
    $this->teamMembershipManager->addMembers($this->team->getName(), [$this->account->getEmail()]);
    $this->setUserPermissions([]);
    $this->setTeamRolePermissionsOnUi(TeamRoleInterface::TEAM_MEMBER_ROLE, []);
    $this->validateTeamAccess();
    $this->validateTeamAppAccess();

    // The user is a member of the team. Check every team member level
    // permission one by one.
    foreach (array_keys(self::TEAM_MEMBER_PERMISSION_MATRIX) as $permission) {
      $this->setTeamRolePermissionsOnUi(TeamRoleInterface::TEAM_MEMBER_ROLE, [$permission]);
      $this->validateTeamAccess();
      $this->validateTeamAppAccess();
    }

    // The user is not a member of the team but every team member operation is
    // enabled. The user has no access to the team and team app related pages.
    $this->setTeamRolePermissionsOnUi(TeamRoleInterface::TEAM_MEMBER_ROLE, array_keys(self::TEAM_MEMBER_PERMISSION_MATRIX));
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
    $this->validateTeamAccess(TRUE);
    $this->validateTeamAppAccess(TRUE);
    $this->validateAccessToAdminRoutes(TRUE);
  }

  /**
   * Tests team roles related UIs, permissions.
   */
  protected function teamRoleAccessTest() {
    // Ensure the current user is anonymous.
    if ($this->loggedInUser) {
      $this->drupalLogout();
    }
    // The user is a member of the team and it has no teams related permission.
    // The user has the default "member" role in the team, the default member
    // role has no permissions.
    $this->setUserPermissions([]);
    $this->setTeamRolePermissionsOnUi(TeamRoleInterface::TEAM_MEMBER_ROLE, []);
    $this->teamMembershipManager->addMembers($this->team->getName(), [$this->account->getEmail()]);

    // Create roles for every team membership level permission.
    $this->drupalLogin($this->rootUser);
    foreach (array_keys(self::TEAM_MEMBER_PERMISSION_MATRIX) as $permission) {
      $this->drupalGet(Url::fromRoute('entity.team_role.add_form'));
      $this->submitForm([
        'label' => $permission,
        'id' => $permission,
      ], 'Save');
      $this->setTeamRolePermissionsOnUi($permission, [$permission]);
    }

    // Grant team roles to the team member one by one.
    foreach (array_keys(self::TEAM_MEMBER_PERMISSION_MATRIX) as $permission) {
      $this->drupalLogin($this->rootUser);
      $this->teamMemberRoleStorage->addTeamRoles($this->account, $this->team, [$permission]);
      $this->drupalLogin($this->account);
      $this->validateTeamAccess();
      $this->validateTeamAppAccess();
      $this->drupalLogout();
    }

    // Revoke team roles from the team member one by one.
    foreach (array_keys(self::TEAM_MEMBER_PERMISSION_MATRIX) as $permission) {
      $this->drupalLogin($this->rootUser);
      $this->teamMemberRoleStorage->addTeamRoles($this->account, $this->team, [$permission]);
      $this->drupalLogin($this->account);
      $this->validateTeamAccess();
      $this->validateTeamAppAccess();
      $this->drupalLogout();
    }
  }

  /**
   * Tests apigee_edge_teams_test module.
   */
  protected function teamExpansionTest() {
    // Ensure that the apigee_edge_teams_test module properly extends the team
    // role permission UI.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet(Url::fromRoute('apigee_edge_teams.settings.team.permissions'));
    // Check whether the permission group labels and the permission labels and
    // descriptions are visible.
    $this->assertSession()->pageTextContains('Apigee Teams: Testing');
    $this->assertSession()->pageTextContains('Team permission test');
    $this->assertSession()->pageTextContains('Team permission test 1');
    $this->assertSession()->pageTextContains('This is the 1st team test permission.');
    $this->assertSession()->pageTextContains('Team permission test 2');
    $this->assertSession()->pageTextContains('Team permission test 3');
    $this->assertSession()->pageTextContains('This is the 3rd team test permission.');
    $this->assertSession()->pageTextContains('Team permission test 4');

    // Change the username to grant every team permission to the user in
    // apigee_edge_teams_test_apigee_edge_teams_developer_permissions_by_team_alter().
    // It is not necessary to save the developer associated with this user.
    $this->disableUserPresave();
    $this->account->setUsername(APIGEE_EDGE_TEAMS_TEST_SPECIAL_USERNAME_WITH_ALL_TEAM_PERMISSIONS);
    $this->account->save();
    $this->enableUserPresave();

    // Make sure that the user is no longer a member of the team anymore.
    if (in_array($this->team->id(), $this->teamMembershipManager->getTeams($this->account->getEmail()))) {
      $this->teamMembershipManager->removeMembers($this->team->getName(), [$this->account->getEmail()]);
    }
    $this->assertNotContains($this->team->id(), $this->teamMembershipManager->getTeams($this->account->getEmail()));

    $this->drupalLogin($this->account);
    // Even if the account is not member of the team it should have access all
    // team related UIs that a team permission can grant access.
    // (The user can still not CRUD teams but it can access to the teams list.)
    $this->validateTeamAccess();
    $this->validateTeamAppAccess();
    // Now it can not just access to the team list, but it can see all teams
    // in the list and access all team related UIs because it has all team
    // permissions.
    $this->state->set(APIGEE_EDGE_TEAMS_TEST_SPECIAL_USERNAME_CAN_VIEW_ANY_TEAM_STATE_KEY, TRUE);
    $this->validateAccess($this->team->toUrl(), Response::HTTP_OK);
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
      foreach (array_keys(self::TEAM_PERMISSION_MATRIX) as $permission) {
        if ($this->account->hasPermission($permission)) {
          $route_ids_with_access = array_merge($route_ids_with_access, self::TEAM_PERMISSION_MATRIX[$permission]);
        }
      }

      if ($this->drupalUserIsLoggedIn($this->account)) {
        // Authenticated users always have access to team collection.
        $route_ids_with_access[] = 'collection';
      }

      // Team members always have access to the team canonical page.
      if (in_array($this->account->getEmail(), $this->teamMembershipManager->getMembers($this->team->getName()))) {
        $route_ids_with_access[] = 'canonical';
      }

      // The developer is not necessarily a member of the team.
      if (in_array('team_manage_members', $this->teamPermissionHandler->getDeveloperPermissionsByTeam($this->team, $this->account))) {
        $route_ids_with_access = array_merge($route_ids_with_access, self::TEAM_MEMBER_PERMISSION_MATRIX['team_manage_members']);
      }
    }

    foreach ($this->teamEntityRoutes as $route_id => $route) {
      $short_route_id = str_replace('entity.team.', '', $route_id);
      $rel = str_replace('_', '-', $short_route_id);
      // First try to use the entity to generate the url - and with that
      // make sure the url parameter resolver works on the entity.
      if ($this->team->hasLinkTemplate($rel)) {
        $url = $this->team->toUrl($rel);
        if (in_array($short_route_id, $route_ids_with_access)) {
          $this->validateAccess($url, Response::HTTP_OK);
        }
        else {
          $this->validateAccess($url, Response::HTTP_FORBIDDEN);
        }
      }
      else {
        // If the route is not registered as link in entity links - because
        // it contains a route parameter that the entity can not resolve -
        // fallback to the URL resolver. At this time these are the member.edit
        // and member.remove routes. Use a developer parameter in the route
        // which belongs to a member of the team and which belongs to a
        // non-member of the team and an email address of a non-existing
        // developer.
        $params = ['team' => $this->team->id()];
        $this->validateAccess(Url::fromRoute($route_id, $params + ['developer' => $this->teamMemberAccount->getEmail()]), in_array($short_route_id, $route_ids_with_access) ? Response::HTTP_OK : Response::HTTP_FORBIDDEN);
        $this->validateAccess(Url::fromRoute($route_id, $params + ['developer' => $this->nonTeamMemberAccount->getEmail()]), Response::HTTP_FORBIDDEN);
        $this->validateAccess(Url::fromRoute($route_id, $params + ['developer' => $this->randomMachineName() . '@example.com']), Response::HTTP_NOT_FOUND);
      }
    }
  }

  /**
   * Checks whether the user has access to team app pages.
   *
   * @param bool $admin_access
   *   TRUE if the user has access to every team app page.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function validateTeamAppAccess(bool $admin_access = FALSE) {
    $route_ids_with_access = [];

    if ($admin_access) {
      $route_ids_with_access = array_map(function (string $route_id) {
        return str_replace('entity.team_app.', '', $route_id);
      }, array_keys($this->teamAppEntityRoutes));
    }
    else {
      // The developer is not necessarily a member of the team.
      foreach (array_keys(self::TEAM_MEMBER_PERMISSION_MATRIX) as $permission) {
        if (in_array($permission, $this->teamPermissionHandler->getDeveloperPermissionsByTeam($this->team, $this->account))) {
          $route_ids_with_access = array_merge($route_ids_with_access, self::TEAM_MEMBER_PERMISSION_MATRIX[$permission]);
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
        $this->validateAccess($url, Response::HTTP_OK);
      }
      else {
        $this->validateAccess($url, Response::HTTP_FORBIDDEN);
      }
    }
  }

  /**
   * Checks whether the user has access to the given URL.
   *
   * @param \Drupal\Core\Url $url
   *   The Url object to check.
   * @param int $expected_response_status_code
   *   The expected HTTP response status code.
   */
  protected function validateAccess(Url $url, int $expected_response_status_code) {
    $this->drupalGet($url->toString());
    $code = $this->getSession()->getStatusCode();
    $current_user_roles = implode(', ', $this->account->getRoles());
    $this->assertEquals($expected_response_status_code, $code, "Visited path: {$url->getInternalPath()}. User roles: {$current_user_roles}");
  }

  /**
   * Checks whether the user has access to admin routes.
   *
   * @param bool $user_has_access
   *   TRUE if the user has access to admin routes, else FALSE.
   */
  protected function validateAccessToAdminRoutes(bool $user_has_access) {
    foreach (self::ADMIN_ROUTES as $route_name) {
      $this->validateAccess(Url::fromRoute($route_name), $user_has_access ? Response::HTTP_OK : Response::HTTP_FORBIDDEN);
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
   * Sets team role permissions.
   *
   * The team role permission admin UI is tested properly while changing the
   * permissions.
   *
   * @param string $role_name
   *   The ID of a team role.
   * @param array $permissions
   *   Team role permissions to enable.
   */
  protected function setTeamRolePermissionsOnUi(string $role_name, array $permissions) {
    // Save the original logged in user if there is any.
    // Note: The account switcher service is not working as it is expected this
    // is the reason why we need this workaround.
    $oldNotRootLoggedInUser = NULL;
    if ($this->loggedInUser && $this->loggedInUser->id() != $this->rootUser->id()) {
      $oldNotRootLoggedInUser = clone $this->loggedInUser;
    }

    // Save permissions with admin user.
    if ($oldNotRootLoggedInUser === NULL || $oldNotRootLoggedInUser->id() !== $this->rootUser->id()) {
      $this->drupalLogin($this->rootUser);
    }

    $permission_changes = [];
    foreach (array_keys(self::TEAM_MEMBER_PERMISSION_MATRIX) as $permission) {
      $permission_changes["{$role_name}[{$permission}]"] = in_array($permission, $permissions);
    }

    $this->drupalGet(Url::fromRoute('apigee_edge_teams.settings.team.permissions'));
    $this->submitForm($permission_changes, 'Save permissions');
    // Dump permission configuration to the HTML output.
    $this->drupalGet(Url::fromRoute('apigee_edge_teams.settings.team.permissions'));
    // Because changes made on the UI therefore _this_ instance of the team role
    // storage must be cleared manually.
    $this->teamRoleStorage->resetCache([$role_name]);
    // Log back in with the old, not root user.
    if ($oldNotRootLoggedInUser) {
      if ($oldNotRootLoggedInUser->id() === $this->account->id()) {
        $this->drupalLogin($this->account);
      }
      else {
        throw new \Exception("Unable to switch back to the originally logged user because it was neither the root user nor the simple authenticated user. Its user id: {$oldNotRootLoggedInUser->id()}.");
      }
    }
  }

}
