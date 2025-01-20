<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\apigee_edge\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a secret element.
 *
 * @RenderElement("apigee_secret")
 */
/**
 * Provides a secret element.
 *
 * @todo class RenderElement is deprecated for Drupal 10.3 & is removed from drupal:12.0. Use \Drupal\Core\Render\Element\RenderElementBase instead. https://www.drupal.org/node/3436275
 * @phpstan-ignore-next-line
 */
class ApigeeSecret extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'apigee_secret',
      '#value' => NULL,
    ];
  }

}
