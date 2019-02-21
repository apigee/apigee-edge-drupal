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

namespace Drupal\apigee_edge_teams\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity\EntityPermissionProviderInterface;

/**
 * Permission provider for Team App entities.
 */
final class TeamAppPermissionProvider implements EntityPermissionProviderInterface {

  use StringTranslationTrait;

  /**
   * The id of the Manage Team Apps permission.
   *
   * @var string
   */
  public const MANAGE_TEAM_APPS_PERMISSION = 'manage team apps';

  /**
   * {@inheritdoc}
   */
  public function buildPermissions(EntityTypeInterface $entity_type) {
    $permissions = [];

    $team_app_plural_label = $entity_type->getPluralLabel();

    $permissions[static::MANAGE_TEAM_APPS_PERMISSION] = [
      'title' => $this->t('Manage @type', [
        '@type' => $team_app_plural_label,
      ]),
      'description' => $this->t('Allows to manage all @team_apps in the system.', [
        '@team_apps' => $team_app_plural_label,
      ]),
      'restrict access' => TRUE,
    ];

    foreach ($permissions as $name => $permission) {
      $permissions[$name]['provider'] = $entity_type->getProvider();
      // TranslatableMarkup objects don't sort properly.
      $permissions[$name]['title'] = (string) $permission['title'];
    }

    return $permissions;
  }

}
