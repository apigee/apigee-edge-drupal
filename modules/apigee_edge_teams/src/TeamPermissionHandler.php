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

use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\apigee_edge_teams\Entity\TeamRoleInterface;
use Drupal\apigee_edge_teams\Exception\InvalidArgumentException;
use Drupal\apigee_edge_teams\Structure\TeamPermission;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Discovery\YamlDiscovery;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides the available team permissions based on yml files.
 *
 * To define team permissions you can use a $module.team_permissions.yml file.
 *
 * If your module needs to define dynamic permissions you can use the
 * permission_providers key to declare a class that implements
 * DynamicTeamPermissionProviderInterface that will return an array of
 * team permissions. Each item in the array can contain
 * the same keys as an entry in $module.team_permissions.yml.
 *
 * Here is an example (comments have been added):
 *
 * @code
 * # The key is the team permission machine name, and is required.
 * manage team members:
 *   # (required) The human-readable name of the team permission, to be shown
 *   # on the team permission administration page.
 *   title: 'Manage team members'
 *   # (optional) Additional description for the team permission used in the UI.
 *   description: 'Add/remove team members.'
 *   # (optional) The category that the team permission belongs (ex.: Team Apps)
 *   # to be shown on the team permission administration page.
 *   # Default is the name of the provider module.
 *   category: 'Members'
 *
 * # An array of classes used to generate dynamic team permissions.
 * permission_providers:
 *   # Each item in the array must implement
 *   # DynamicTeamPermissionProviderInterface interface.
 *   - Drupal\apigee_edge_teams\DefaultTeamPermissionsProvider
 * @endcode
 *
 * Based on Drupal core's PermissionHandler with a bunch of improvements.
 *
 * @see \Drupal\user\PermissionHandler
 * @see \Drupal\apigee_edge_teams\DynamicTeamPermissionProviderInterface
 */
final class TeamPermissionHandler implements TeamPermissionHandlerInterface {

  use StringTranslationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  private $classResolver;

  /**
   * The YAML discovery object instance cache.
   *
   * Yaml discovery to find all .team_permissions.yml files.
   *
   * Use getYamlDiscovery() instead.
   *
   * @var \Drupal\Core\Discovery\YamlDiscovery|null
   */
  private $yamlDiscovery;

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  private $teamMembershipManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * TeamPermissionHandler constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ClassResolverInterface $class_resolver, TeamMembershipManagerInterface $team_membership_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->moduleHandler = $module_handler;
    $this->classResolver = $class_resolver;
    $this->teamMembershipManager = $team_membership_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions(): array {
    $all_permissions = $this->buildPermissionsYaml();

    return $this->sortPermissions($all_permissions);
  }

  /**
   * {@inheritdoc}
   */
  public function getDeveloperPermissionsByTeam(TeamInterface $team, AccountInterface $account): array {
    if ($account->isAnonymous()) {
      throw new InvalidArgumentException('Anonymous user can not be member of a team.');
    }

    $permissions = [];
    try {
      $developer_team_ids = $this->teamMembershipManager->getTeams($account->getEmail());
    }
    catch (\Exception $e) {
      $developer_team_ids = [];
    }
    // Only add team membership based permissions to the list if the developer
    // is still member of the team in Apigee Edge.
    if (in_array($team->id(), $developer_team_ids)) {
      /** @var \Drupal\apigee_edge_teams\Entity\TeamRoleInterface $member_role */
      $member_role = $this->entityTypeManager->getStorage('team_role')->load(TeamRoleInterface::TEAM_MEMBER_ROLE);
      $permissions += $member_role->getPermissions();
      /** @var \Drupal\apigee_edge_teams\Entity\TeamMemberRoleInterface|null $dev_team_role */
      $dev_team_role = $this->entityTypeManager->getStorage('team_member_role')->loadByDeveloperAndTeam($account, $team);
      if ($dev_team_role) {
        foreach ($dev_team_role->getTeamRoles() as $role) {
          $permissions = array_merge($permissions, $role->getPermissions());
        }
      }
    }

    // Allow 3rd-party modules to modify a developer's team-level permissions
    // withing a team.
    // WARNING: Alter hooks gets called even if the developer is not member
    // of a team (company) in Apigee Edge. This allows to grant team-level
    // permissions to a developer (Drupal user) to a team without adding it as a
    // member to the team (company) in Apigee Edge. (Ex.: for team management
    // purposes, etc.)
    $this->moduleHandler->alter('apigee_edge_teams_developer_permissions_by_team', $permissions, $team, $account);

    return array_unique($permissions);
  }

  /**
   * Gets the YAML discovery.
   *
   * @return \Drupal\Core\Discovery\YamlDiscovery
   *   The YAML discovery.
   */
  private function getYamlDiscovery(): YamlDiscovery {
    if (!isset($this->yamlDiscovery)) {
      $this->yamlDiscovery = new YamlDiscovery('team_permissions', $this->moduleHandler->getModuleDirectories());
    }
    return $this->yamlDiscovery;
  }

  /**
   * Builds all team permissions provided by .team_permissions.yml files.
   *
   * @return \Drupal\apigee_edge_teams\Structure\TeamPermission[]
   *   Array of team permissions.
   *
   * @throws \Drupal\apigee_edge_teams\Exception\InvalidArgumentException
   *   If permission provider class does not implement
   *   DynamicTeamPermissionProviderInterface.
   * @throws \InvalidArgumentException
   *   If permission provider class does not exist.
   */
  protected function buildPermissionsYaml(): array {
    $all_permissions = [];
    $all_dynamic_permissions = [];
    $module_names = $this->getModuleNames();

    foreach ($this->getYamlDiscovery()->findAll() as $provider => $permissions) {
      if (isset($permissions['permission_providers'])) {
        foreach ($permissions['permission_providers'] as $fqcn) {
          // Thanks for class resolver permission providers can implement
          // ContainerInjectionInterface and access services. This should be
          // a better approach than what PermissionHandler does with controller
          // resolver.
          $permission_provider = $this->classResolver->getInstanceFromDefinition($fqcn);
          if ($permission_provider instanceof DynamicTeamPermissionProviderInterface) {
            /** @var \Drupal\apigee_edge_teams\Structure\TeamPermission $dynamic_permission */
            foreach ($permission_provider->permissions() as $dynamic_permission) {
              $all_dynamic_permissions[$dynamic_permission->getName()] = $dynamic_permission;
            }
          }
          else {
            throw new InvalidArgumentException(sprintf('%s must implement %s.', $fqcn, DynamicTeamPermissionProviderInterface::class));
          }
        }

        unset($permissions['permission_providers']);
      }

      foreach ($permissions as $name => $permission) {
        if (!is_array($permission)) {
          $permission = [
            'title' => $permission,
          ];
        }
        $permission['name'] = $name;
        $permission['title'] = $this->t($permission['title']);
        $permission['description'] = isset($permission['description']) ? $this->t($permission['description']) : NULL;
        $permission['category'] = !empty($permission['category']) ? $this->t($permission['category']) : $this->t($module_names[$provider]);
        $all_permissions[$name] = $permission;
      }
    }

    return array_map(function (array $permission) {
      return new TeamPermission($permission['name'], $permission['title'], $permission['category'], $permission['description']);
    }, $all_permissions) + $all_dynamic_permissions;
  }

  /**
   * Sorts the given team permissions by category and title.
   *
   * @param array $all_permissions
   *   The team permissions to be sorted.
   *
   * @return \Drupal\apigee_edge_teams\Structure\TeamPermission[]
   *   Sorted team permissions.
   */
  protected function sortPermissions(array $all_permissions = []) {
    uasort($all_permissions, function (TeamPermission $permission_a, TeamPermission $permission_b) {
      if ($permission_a->getCategory() == $permission_b->getCategory()) {
        return $permission_a->getLabel() > $permission_b->getLabel();
      }
      else {
        return $permission_a->getCategory() > $permission_b->getCategory();
      }
    });
    return $all_permissions;
  }

  /**
   * Returns all module names.
   *
   * @return string[]
   *   Returns the human readable names of all modules keyed by machine name.
   */
  protected function getModuleNames(): array {
    $modules = [];
    foreach (array_keys($this->moduleHandler->getModuleList()) as $module) {
      $modules[$module] = $this->moduleHandler->getName($module);
    }
    asort($modules);
    return $modules;
  }

}
