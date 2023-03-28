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

namespace Drupal\apigee_edge_teams\Entity\Controller;

use Apigee\Edge\Api\ApigeeX\Controller\AppGroupAppCredentialController;
use Apigee\Edge\Api\Management\Controller\AppCredentialController as EdgeAppCredentialController;
use Apigee\Edge\Api\Management\Controller\CompanyAppCredentialController;
use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerBase;
use Drupal\apigee_edge\Entity\Controller\OrganizationController;
use Drupal\apigee_edge\Event\AbstractAppCredentialEvent;

/**
 * Definition of the team app credential controller service.
 *
 * This integrates the Management API's Company app credential controller
 * from the SDK's with Drupal.
 */
final class TeamAppCredentialController extends AppCredentialControllerBase implements TeamAppCredentialControllerInterface {

  /**
   * {@inheritdoc}
   */
  protected function decorated(): EdgeAppCredentialController {
    if (!isset($this->instances[$this->owner][$this->appName])) {
      $organizationController = new OrganizationController($this->connector);
      // Checks whether the organization is Edge or ApigeeX organization.
      if ($organizationController->isOrganizationApigeeX()) {
        $this->instances[$this->owner][$this->appName] = new AppGroupAppCredentialController($this->connector->getOrganization(), $this->owner, $this->appName, $this->connector->getClient());
      }
      else {
        $this->instances[$this->owner][$this->appName] = new CompanyAppCredentialController($this->connector->getOrganization(), $this->owner, $this->appName, $this->connector->getClient());
      }
    }
    return $this->instances[$this->owner][$this->appName];
  }

  /**
   * {@inheritdoc}
   */
  protected function getAppType(): string {
    return AbstractAppCredentialEvent::APP_TYPE_TEAM;
  }

}
