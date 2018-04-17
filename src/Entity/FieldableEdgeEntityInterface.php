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

use Apigee\Edge\Structure\AttributesProperty;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Interface for fieldable Edge entities.
 */
interface FieldableEdgeEntityInterface extends FieldableEntityInterface, EdgeEntityInterface {

  /**
   * Converts a field name to an attribute name.
   *
   * @param string $field_name
   *   Machine name of a field.
   *
   * @return string
   *   Name of the mapped attribute.
   */
  public function getAttributeName(string $field_name): string;

  /**
   * Converts an attribute name to a field name.
   *
   * @param string $attribute_name
   *   Name of an attribute.
   *
   * @return string
   *   Machine name of the mapped field.
   */
  public function getFieldName(string $attribute_name): string;

  /**
   * Gets field value from the related attribute.
   *
   * @param string $field_name
   *   Name of a field in Drupal.
   * @param \Apigee\Edge\Structure\AttributesProperty $attributesProperty
   *   Attribute property that contains the attributes on an entity.
   *
   * @return mixed|null
   *   Field value from related attribute. It returns NULL if field does
   *   not have an attribute on the entity or its value is actually NULL.
   */
  public function getFieldValueFromAttribute(string $field_name, AttributesProperty $attributesProperty);

}
