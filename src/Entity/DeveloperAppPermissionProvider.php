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

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Permission provider for developer app entities.
 */
class DeveloperAppPermissionProvider extends EdgeEntityPermissionProviderBase {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityTypePermissions(EntityTypeInterface $entity_type) {
    $permissions = parent::buildEntityTypePermissions($entity_type);
    $entity_type_id = $entity_type->id();

    $permissions["analytics any {$entity_type_id}"] = [
      'title' => $this->t('View any @type analytics', [
        '@type' => $entity_type->getSingularLabel(),
      ]),
    ];
    $permissions["analytics own {$entity_type_id}"] = [
      'title' => $this->t('View own @type analytics', [
        '@type' => $entity_type->getPluralLabel(),
      ]),
    ];

    return $permissions;
  }

}
