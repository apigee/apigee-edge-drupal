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

namespace Drupal\apigee_edge\Annotation;

use Drupal\apigee_edge\Entity\EdgeEntityType as EntityEdgeEntityType;
use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an Apigee Edge entity type annotation object.
 *
 * The annotation properties of entity types are found on
 * \Drupal\apigee_edge\Entity\EdgeEntityType and are accessed using
 * get/set methods defined in
 * \Drupal\apigee_edge\Entity\EdgeEntityTypeInterface.
 *
 * @Annotation
 */
class EdgeEntityType extends EntityType {

  /**
   * {@inheritdoc}
   */
  public $entity_type_class = EntityEdgeEntityType::class;

  /**
   * {@inheritdoc}
   */
  public $group = 'apigee_edge';

  /**
   * {@inheritdoc}
   */
  public function get() {
    $this->definition['group_label'] = new TranslatableMarkup('Apigee Edge', [], ['context' => 'Entity type group']);

    return parent::get();
  }

}
