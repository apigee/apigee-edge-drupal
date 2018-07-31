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

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Url;

/**
 * Provides a trait for checking developer app's callback URL.
 */
trait DeveloperAppCallbackUrlCheckTrait {

  /**
   * Checks whether the developer app's Callback URL value is valid.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   The developer app entity.
   * @param string $view_mode
   *   The view mode that should be used to display the developer app entity.
   *
   * @see \Drupal\apigee_edge\Entity\DeveloperApp::set()
   */
  protected function checkCallbackUrl(DeveloperAppInterface $developer_app, $view_mode) {
    $developer_app_view_display = EntityViewDisplay::load("developer_app.developer_app.{$view_mode}");
    // If the Callback URL field is enabled then check its value.
    if ($developer_app_view_display->getComponent('callbackUrl') !== NULL) {
      // If the property value and the field value are different then the
      // callback URL is not a valid URL.
      if ($developer_app->getCallbackUrl() !== $developer_app->get('callbackUrl')->value) {
        try {
          Url::fromUri($developer_app->getCallbackUrl());
        }
        catch (\Exception $exception) {
          \Drupal::messenger()->addWarning(t('The Callback URL value should be fixed. @message', [
            '@message' => $exception->getMessage(),
          ]));
        }
      }
    }
  }

}
