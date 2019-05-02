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

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Default view builder class for Apigee Edge entities.
 */
class EdgeEntityViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getBuildDefaults(EntityInterface $entity, $view_mode): array {
    $defaults = parent::getBuildDefaults($entity, $view_mode);

    // Simplified path to the entity.
    $defaults['#entity'] = $entity;

    // Use the generic apigee_entity theme if the entity type specific theme
    // does not exist. Drupal core does not provide a default template for
    // entities yet. This solution also allows to change the default, non-entity
    // type specific theme that is being used if the entity type specific does
    // not exist.
    // When 2808481 gets in core, the default theme becomes the "entity".
    // Because of BC we can not use that.
    // @see apigee-entity.html.twig
    // @see https://www.drupal.org/project/drupal/issues/2808481
    if (!isset($defaults['#theme']) || $defaults['#theme'] === 'entity') {
      $defaults['#theme'] = 'apigee_entity';
    }

    return $defaults;
  }

}
