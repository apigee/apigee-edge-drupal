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

use Apigee\Edge\Api\Management\Entity\AppInterface as EdgeAppInterface;

/**
 * Defines an interface for App entity objects.
 */
interface AppInterface extends EdgeAppInterface, AttributesAwareFieldableEdgeEntityBaseInterface {

  /**
   * Returns the id of the app owner from the app entity.
   *
   * Return value could be either the developer id or the company name.
   *
   * @return string
   *   Id of the app owner, or null if the app is new.
   */
  public function getAppOwner(): ?string;

  /**
   * Sets the app owner's property value on an app.
   *
   * @param string $owner
   *   The owner of the app. Developer id (uuid) or team (company) name.
   */
  public function setAppOwner(string $owner): void;

}
