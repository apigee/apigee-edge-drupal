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
interface FieldableEdgeEntityInterface extends \IteratorAggregate, FieldableEntityInterface, EdgeEntityInterface {

  /**
   * Gets field value from the related attribute.
   *
   * @param string $field_name
   *   Name of a field in Drupal.
   * @param \Apigee\Edge\Structure\AttributesProperty $attributes_property
   *   Attribute property that contains the attributes on an entity.
   *
   * @return mixed|null
   *   Field value from related attribute. It returns NULL if field does
   *   not have an attribute on the entity or its value is actually NULL.
   */
  public function getFieldValueFromAttribute(string $field_name, AttributesProperty $attributes_property);

}
