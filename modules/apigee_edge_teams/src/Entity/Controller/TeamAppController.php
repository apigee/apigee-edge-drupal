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

use Apigee\Edge\Api\ApigeeX\Controller\AppByOwnerControllerInterface as XAppByOwnerControllerInterface;
use Apigee\Edge\Api\ApigeeX\Controller\AppGroupAppController as EdgeAppGroupAppController;
use Apigee\Edge\Api\Management\Controller\AppByOwnerControllerInterface as EdgeAppByOwnerControllerInterface;
use Apigee\Edge\Api\Management\Controller\CompanyAppController as EdgeCompanyAppController;
use Drupal\apigee_edge\Entity\Controller\AppByOwnerController;

/**
 * Definition of the Team app controller service.
 *
 * We call company/appgroup apps as team apps in Drupal.
 */
final class TeamAppController extends AppByOwnerController implements TeamAppControllerInterface {

  /**
   * {@inheritdoc}
   */
  protected function decorated(): EdgeAppByOwnerControllerInterface|XAppByOwnerControllerInterface {
    if (!isset($this->instances[$this->owner])) {
      // Checks whether the organization is Edge or ApigeeX organization.
      if ($this->organizationController->isOrganizationApigeeX()) {
        $this->instances[$this->owner] = new EdgeAppGroupAppController($this->connector->getOrganization(), $this->owner, $this->connector->getClient(), NULL, $this->organizationController);
      }
      else {
        $this->instances[$this->owner] = new EdgeCompanyAppController($this->connector->getOrganization(), $this->owner, $this->connector->getClient(), NULL, $this->organizationController);
      }
    }
    return $this->instances[$this->owner];
  }

  /**
   * {@inheritdoc}
   */
  public function getCompanyName(): string {
    /** @var \Apigee\Edge\Api\Management\Controller\CompanyAppControllerInterface $decorated */
    $decorated = $this->decorated();
    return $decorated->getCompanyName();
  }

  /**
   * {@inheritdoc}
   */
  public function getAppGroup(): string {
    /** @var \Apigee\Edge\Api\ApigeeX\Controller\AppGroupAppControllerInterface $decorated */
    $decorated = $this->decorated();
    return $decorated->getAppGroup();
  }

}
