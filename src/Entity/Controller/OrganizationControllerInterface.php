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

use Apigee\Edge\Api\Management\Controller\OrganizationControllerInterface as EdgeOrganizationControllerInterface;

/**
 * Base definition of the Organization controller service in Drupal.
 */
interface OrganizationControllerInterface extends EdgeOrganizationControllerInterface {
  /**
   * Returns the Data Residency Endpoint for the organization.
   *
   * This is only available for Apigee X/Hybrid organizations.
   *
   * @return string
   *   Returns location based endpoint uri.
   */
  public function getDataResidencyEndpoint(string $organizationName): string;

  /**
   * Checks whether the organization is Edge or ApigeeX organization.
   *
   * @return bool
   *   TRUE if the value of the property is "true", false otherwise.
   */
  public function isOrganizationApigeeX(): bool;

}
