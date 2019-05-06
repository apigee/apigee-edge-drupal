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

use Drupal\Core\Render\Element;
use Drupal\Core\Url;

/**
 * Common app view builder for developer- and company (team) apps.
 */
class AppViewBuilder extends EdgeEntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildMultiple(array $build_list) {
    $results = parent::buildMultiple($build_list);
    foreach (Element::children($results) as $key) {
      /** @var \Drupal\apigee_edge\Entity\AppInterface $app */
      $app = $results[$key]["#{$this->entityTypeId}"];
      // If the callback field is visible, display an error message if the
      // callback url field value does not contain a valid URI.
      if (array_key_exists('callbackUrl', $results[$key]) && !empty($app->getCallbackUrl()) && $app->getCallbackUrl() !== $app->get('callbackUrl')->value) {
        try {
          Url::fromUri($app->getCallbackUrl());
        }
        catch (\Exception $exception) {
          $results[$key]['callback_url_error'] = [
            '#theme' => 'status_messages',
            '#message_list' => [
              'warning' => [$this->t('The @field value should be fixed. @message', [
                '@field' => $app->getFieldDefinition('callbackUrl')->getLabel(),
                '@message' => $exception->getMessage(),
              ]),
              ],
            ],
              // Display it on the top of the view.
            '#weight' => -100,
          ];
        }
      }
    }
    return $results;
  }

}
