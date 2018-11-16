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

use Apigee\Edge\Api\Management\Entity\DeveloperInterface as EdgeDeveloperInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Defines an interface for develper entity objects.
 */
interface DeveloperInterface extends EdgeDeveloperInterface, EdgeEntityInterface, EntityOwnerInterface {

  /**
   * Resets the original email.
   */
  public function resetOriginalEmail() : void;

  /**
   * Set status of the developer.
   *
   * @param string $status
   *   Status of the entity.
   */
  public function setStatus(string $status): void;

  /**
   * Returns the original (unmodified) email address of a developer.
   *
   * @return null|string
   *   The original (unmodified) email address of a developer.
   */
  public function getOriginalEmail(): ?string;

}
