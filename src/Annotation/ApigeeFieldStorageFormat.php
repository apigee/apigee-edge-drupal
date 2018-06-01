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

use Drupal\Component\Annotation\Plugin;

/**
 * @Annotation
 */
class ApigeeFieldStorageFormat extends Plugin {

  /**
   * @var string
   */
  public $id;

  /**
   * @var string
   */
  public $class;

  /**
   * @var string
   */
  public $provider;

  /**
   * @var string
   */
  public $label;

  /**
   * List of field types where this plugin is appropriate.
   *
   * If one item is '*' then it will be applied on any field type.
   *
   * @var array
   */
  public $fields;

  /**
   * Weight of this plugin.
   *
   * The plugins will be sorted by weight and will be tried to be applied in
   * that order.
   *
   * @var int
   */
  public $weight;

}
