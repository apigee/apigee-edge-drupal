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

namespace Drupal\apigee_edge;

/**
 * Defines an interface for credentials classes.
 */
interface CredentialsInterface {

  /**
   * Gets the Edge API endpoint.
   *
   * @return string
   *   Apigee Edge endpoint URI.
   */
  public function getEndpoint(): string;

  /**
   * Gets the API username.
   *
   * @return string
   *   The API username.
   */
  public function getUsername(): string;

  /**
   * Gets the name of the organization.
   *
   * @return string
   *   The name of the organization.
   */
  public function getOrganization(): string;

  /**
   * Gets the API password.
   *
   * @return string
   *   The API password.
   */
  public function getPassword(): string;

  /**
   * Sets the Edge API endpoint.
   *
   * @param string $endpoint
   *   Apigee Edge endpoint URI.
   */
  public function setEndpoint(string $endpoint);

  /**
   * Sets the name of the organization.
   *
   * @param string $organization
   *   The name of the organization.
   */
  public function setOrganization(string $organization);

  /**
   * Sets the API username.
   *
   * @param string $username
   *   The API username.
   */
  public function setUsername(string $username);

  /**
   * Sets the API password.
   *
   * @param string $password
   *   The API password.
   */
  public function setPassword(string $password);

  /**
   * Checks whether this object is empty.
   *
   * @return bool
   *   TRUE if the credentials are not initialized.
   */
  public function empty() : bool;

}
