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

namespace Drupal\apigee_edge;

/**
 * Field-attribute converter service definition.
 */
interface FieldAttributeConverterInterface {

  /**
   * Returns the name of the mapped attribute to a field.
   *
   * @param string $field_name
   *   Name of a field.
   *
   * @return string
   *   Name of the mapped attribute.
   */
  public function getAttributeName(string $field_name): string;

  /**
   * Returns the name of the mapped field to an attribute.
   *
   * @param string $attribute_name
   *   Name of an attribute.
   *
   * @return string
   *   Name of the mapped field.
   */
  public function getFieldName(string $attribute_name): string;

}
