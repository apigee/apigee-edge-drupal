<?php

/**
 * Copyright 2020 Google Inc.
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

/**
 * Defines an interface for an app warnings checker service.
 */
interface AppWarningsCheckerInterface {

  /**
   * Checks credentials of an app and returns warnings about them.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity to be checked.
   *
   * @return array
   *   An associative array that contains information about the revoked
   *   credentials and revoked or pending API products in a credential.
   */
  public function getWarnings(AppInterface $app): array;

}
