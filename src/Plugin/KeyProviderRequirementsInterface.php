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

namespace Drupal\apigee_edge\Plugin;

use Drupal\key\KeyInterface;

/**
 * Interface for checking the key provider's requirements.
 */
interface KeyProviderRequirementsInterface {

  /**
   * Checks the requirements of the key provider.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @throws \Drupal\apigee_edge\Exception\KeyProviderRequirementsException
   *   Exception thrown when the requirements of the key provider are not
   *   fulfilled.
   */
  public function checkRequirements(KeyInterface $key): void;

}
