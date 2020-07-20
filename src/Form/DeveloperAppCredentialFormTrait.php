<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\apigee_edge\Form;

use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;

/**
 * Provides form trait for developer app credential.
 */
trait DeveloperAppCredentialFormTrait {

  /**
   * {@inheritdoc}
   */
  protected function appCredentialController(string $owner, string $app_name): AppCredentialControllerInterface {
    return \Drupal::service('apigee_edge.controller.developer_app_credential_factory')->developerAppCredentialController($owner, $app_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->app->toUrl('canonical-by-developer');
  }

}
