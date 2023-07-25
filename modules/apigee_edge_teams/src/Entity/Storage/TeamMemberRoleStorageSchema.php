<?php

/**
 * Copyright 2023 Google Inc.
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

namespace Drupal\apigee_edge_teams\Entity\Storage;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Entity storage class for team member role entities.
 */
class TeamMemberRoleStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);
    // For JSONAPI uuid added in entity_keys, but because it, duplicate
    // uuid field is generated, which cause error while installing team_member table.
    if (!empty($schema['team_member_role']['fields']['uuid'])) {
      foreach ($schema['team_member_role']['fields']['uuid'] as $key => $value) {
        $schema['team_member_role']['fields']['uuid'][$key] = is_array($value) ? $value[0] : $value;
      }
      // Fix to remove duplicate UUID field in primary key.
      if (!empty($schema['team_member_role']['unique keys']['team_member_role_field__uuid__value'][1]) && $schema['team_member_role']['unique keys']['team_member_role_field__uuid__value'][1] == 'uuid') {
        unset($schema['team_member_role']['unique keys']['team_member_role_field__uuid__value'][1]);
      }
    }
    return $schema;
  }

}
