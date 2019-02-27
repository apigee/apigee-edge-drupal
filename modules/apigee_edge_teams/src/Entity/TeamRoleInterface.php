<?php

namespace Drupal\apigee_edge_teams\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Team Role entities.
 */
interface TeamRoleInterface extends ConfigEntityInterface {

  /**
   * Team member team role entity id.
   *
   * @var string
   */
  public const TEAM_MEMBER_ROLE = 'member';

  /**
   * Team admin team role entity id.
   *
   * @var string
   */
  public const TEAM_ADMIN_ROLE = 'admin';

  /**
   * Returns a list of permissions assigned to the role.
   *
   * @return array
   *   The permissions assigned to the role.
   */
  public function getPermissions(): array;

  /**
   * Checks if the role has a permission.
   *
   * @param string $permission
   *   The permission to check for.
   *
   * @return bool
   *   TRUE if the role has the permission, FALSE if not.
   */
  public function hasPermission($permission): bool;

  /**
   * Grant permissions to the role.
   *
   * @param string $permission
   *   The permission to grant.
   */
  public function grantPermission($permission) : void;

  /**
   * Revokes a permissions from the user role.
   *
   * @param string $permission
   *   The permission to revoke.
   */
  public function revokePermission($permission): void;

  /**
   * Returns whether the team role is locked or not.
   *
   * Locked team roles can not be deleted.
   *
   * @return bool
   *   TRUE if team role is locked, FALSE otherwise.
   */
  public function isLocked(): bool;

}
