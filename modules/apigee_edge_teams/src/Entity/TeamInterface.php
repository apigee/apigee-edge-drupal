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

namespace Drupal\apigee_edge_teams\Entity;

use Apigee\Edge\Api\Management\Entity\CompanyInterface;
use Drupal\apigee_edge\Entity\AttributesAwareFieldableEdgeEntityBaseInterface;

/**
 * Defines an interface for Team entity objects.
 */
interface TeamInterface extends CompanyInterface, AttributesAwareFieldableEdgeEntityBaseInterface {

  /**
   * Set status of the team.
   *
   * @param string $status
   *   Status of the entity.
   */
  public function setStatus(string $status): void;

}
