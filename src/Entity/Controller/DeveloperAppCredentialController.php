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

namespace Drupal\apigee_edge\Entity\Controller;

use Apigee\Edge\Api\Management\Controller\AppCredentialController as EdgeAppCredentialController;
use Apigee\Edge\Api\Management\Controller\DeveloperAppCredentialController as EdgeDeveloperAppCredentialController;
use Drupal\apigee_edge\Event\AbstractAppCredentialEvent;

/**
 * Definition of the developer app credential controller service.
 *
 * This integrates the Management API's Developer app credential controller
 * from the SDK's with Drupal.
 */
final class DeveloperAppCredentialController extends AppCredentialControllerBase implements DeveloperAppCredentialControllerInterface {

  /**
   * {@inheritdoc}
   */
  protected function decorated(): EdgeAppCredentialController {
    if (!isset($this->instances[$this->owner][$this->appName])) {
      $this->instances[$this->owner][$this->appName] = new EdgeDeveloperAppCredentialController($this->connector->getOrganization(), $this->owner, $this->appName, $this->connector->getClient());
    }
    return $this->instances[$this->owner][$this->appName];
  }

  /**
   * {@inheritdoc}
   */
  protected function getAppType(): string {
    return AbstractAppCredentialEvent::APP_TYPE_DEVELOPER;
  }

}
