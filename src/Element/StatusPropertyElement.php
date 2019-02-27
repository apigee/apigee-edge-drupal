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

namespace Drupal\apigee_edge\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a status property element.
 *
 * @RenderElement("status_property")
 */
class StatusPropertyElement extends RenderElement {

  /**
   * Indicator status configuration id: OK.
   *
   * @var string
   */
  public const INDICATOR_STATUS_OK = 'indicator_status_ok';

  /**
   * Indicator status configuration id: Warning.
   *
   * @var string
   */
  public const INDICATOR_STATUS_WARNING = 'indicator_status_warning';

  /**
   * Indicator status configuration id: Error.
   *
   * @var string
   */
  public const INDICATOR_STATUS_ERROR = 'indicator_status_error';

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#theme' => 'status_property',
      '#value' => '',
      '#indicator_status' => '',
      '#pre_render' => [
        [$class, 'preRenderStatusProperty'],
      ],
    ];
  }

  /**
   * Prepare the render array for the template.
   *
   * @param array $element
   *   A renderable array.
   *
   * @return array
   *   A renderable array.
   */
  public static function preRenderStatusProperty(array $element): array {
    $element['#attached']['library'][] = 'apigee_edge/apigee_edge.status_property';
    return $element;
  }

}
