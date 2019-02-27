<?php

namespace Drupal\apigee_edge_teams\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Team Role entity.
 *
 * These team roles are supposed to be available for all teams.
 *
 * @ConfigEntityType(
 *   id = "team_role",
 *   label = @Translation("Team Role"),
 *   label_collection = @Translation("Team Roles"),
 *   label_singular = @Translation("team role"),
 *   label_plural = @Translation("team roles"),
 *   label_count = @PluralTranslation(
 *     singular = "@count team role",
 *     plural = "@count team roles",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\apigee_edge_teams\Entity\Storage\TeamRoleStorage",
 *     "access" = "Drupal\apigee_edge_teams\Entity\TeamRoleAccessHandler",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\apigee_edge_teams\Entity\ListBuilder\TeamRoleListBuilder",
 *     "form" = {
 *       "add" = "Drupal\apigee_edge_teams\Form\TeamRoleForm",
 *       "edit" = "Drupal\apigee_edge_teams\Form\TeamRoleForm",
 *       "delete" = "Drupal\apigee_edge_teams\Form\TeamRoleDeleteForm"
 *     },
 *     "route_provider" = {
 *        "html" = "Drupal\apigee_edge_teams\Entity\TeamRoleRouteProvider",
 *     },
 *   },
 *   config_prefix = "team_role",
 *   static_cache = TRUE,
 *   admin_permission = "administer team",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "locked",
 *     "permissions",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/apigee-edge/team-settings/team_role/{team_role}",
 *     "add-form" = "/admin/config/apigee-edge/team-settings/team_role/add",
 *     "edit-form" = "/admin/config/apigee-edge/team-settings/team_role/{team_role}/edit",
 *     "delete-form" = "/admin/config/apigee-edge/team-settings/team_role/{team_role}/delete",
 *     "collection" = "/admin/config/apigee-edge/team-settings/team_role"
 *   }
 * )
 */
class TeamRole extends ConfigEntityBase implements TeamRoleInterface {

  /**
   * The Team Role ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Team Role label.
   *
   * @var string
   */
  protected $label;

  /**
   * The permissions belonging to this role.
   *
   * @var array
   */
  protected $permissions = [];

  /**
   * An indicator that a role can not be removed.
   *
   * @var bool
   */
  protected $locked = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getPermissions(): array {
    return $this->permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission($permission): bool {
    return in_array($permission, $this->getPermissions());
  }

  /**
   * {@inheritdoc}
   */
  public function grantPermission($permission): void {
    if (!$this->hasPermission($permission)) {
      $this->permissions[] = $permission;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function revokePermission($permission): void {
    $this->permissions = array_diff($this->permissions, [$permission]);
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked(): bool {
    return $this->locked;
  }

}
