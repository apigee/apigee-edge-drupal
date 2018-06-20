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

/**
 * Provides utility functions for fieldable Apigee Edge entities and jobs.
 */
trait FieldableEdgeEntityUtilityTrait {

  /**
   * Returns the field UI's field name prefix.
   *
   * @return string
   *   Prefix of the field.
   */
  private static function getFieldPrefix(): string {
    return (string) \Drupal::config('field_ui.settings')->get('field_prefix');
  }

  /**
   * {@inheritdoc}
   */
  public static function getAttributeName(string $field_name): string {
    $field_prefix = static::getFieldPrefix();
    if ($field_prefix && strpos($field_name, $field_prefix) === 0) {
      return substr($field_name, strlen($field_prefix));
    }

    return $field_name;
  }

}
