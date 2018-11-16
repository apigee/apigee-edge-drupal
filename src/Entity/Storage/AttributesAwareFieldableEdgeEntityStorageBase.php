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

namespace Drupal\apigee_edge\Entity\Storage;

/**
 * Storage for fieldable Edge entities that supports attributes.
 */
abstract class AttributesAwareFieldableEdgeEntityStorageBase extends FieldableEdgeEntityStorageBase implements AttributesAwareFieldableEdgeEntityStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition */
    $count = 0;
    /** @var \Apigee\Edge\Entity\Property\AttributesPropertyInterface[] $entities */
    $entities = $this->loadMultiple();
    foreach ($entities as $entity) {
      if ($entity->getAttributeValue($storage_definition->getName()) !== NULL) {
        $count++;
      }
    }

    return $as_bool ? (bool) $count : $count;
  }

}
