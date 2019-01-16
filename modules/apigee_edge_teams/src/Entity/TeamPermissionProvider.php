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
 * Permission provider for Team entities.
 */
final class TeamPermissionProvider implements EntityPermissionProviderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildPermissions(EntityTypeInterface $entity_type) {
    $permissions = [];
    $entity_type_id = $entity_type->id();
    $singular_label = $entity_type->getSingularLabel();
    $plural_label = $entity_type->getPluralLabel();

    $permissions["administer {$entity_type_id}"] = [
      'title' => $this->t('Administer @type', ['@type' => $plural_label]),
      'description' => $this->t('Administer module configure and manage any team and team apps.'),
      'restrict access' => TRUE,
    ];

    $permissions["manage {$entity_type_id} members"] = [
      'title' => $this->t('Manage @type members', ['@type' => $singular_label]),
      'restrict access' => TRUE,
    ];

    $permissions["view any {$entity_type_id}"] = [
      'title' => $this->t('View any @type', [
        '@type' => $plural_label,
      ]),
    ];

    $permissions["create {$entity_type_id}"] = [
      'title' => $this->t('Create @type', [
        '@type' => $plural_label,
      ]),
    ];

    $permissions["update any {$entity_type_id}"] = [
      'title' => $this->t('Update any @type', [
        '@type' => $singular_label,
      ]),
    ];

    $permissions["delete any {$entity_type_id}"] = [
      'title' => $this->t('Delete any @type', [
        '@type' => $singular_label,
      ]),
    ];

    foreach ($permissions as $name => $permission) {
      $permissions[$name]['provider'] = $entity_type->getProvider();
      // TranslatableMarkup objects don't sort properly.
      $permissions[$name]['title'] = (string) $permission['title'];
    }

    return $permissions;
  }

}
