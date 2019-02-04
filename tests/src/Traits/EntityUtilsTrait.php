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

namespace Drupal\Tests\apigee_edge\Traits;

use Drupal\Core\Url;

/**
 * A trait to common functions of Apigee Edge entity tests.
 */
trait EntityUtilsTrait {

  /**
   * Changes and validates the singular and plural aliases of the entity.
   *
   * @param string $entity_type_id
   *   The ID of the entity type.
   * @param string $entity_settings_route_name
   *   The name of the entity settings route.
   */
  protected function changeEntityAliasesAndValidate(string $entity_type_id, string $entity_settings_route_name) {
    $singular = $this->getRandomGenerator()->word(8);
    $plural = $this->getRandomGenerator()->word(8);
    $this->drupalPostForm(Url::fromRoute($entity_settings_route_name), [
      'entity_label_singular' => $singular,
      'entity_label_plural' => $plural,
    ], 'Save configuration');

    \Drupal::entityTypeManager()->clearCachedDefinitions();
    \Drupal::cache('menu')->invalidateAll();

    $type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $this->assertEquals($singular, $type->getSingularLabel());
    $this->assertEquals($plural, $type->getPluralLabel());
  }

}
