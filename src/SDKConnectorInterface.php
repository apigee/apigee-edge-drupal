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

use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Apigee\Edge\HttpClient\ClientInterface;

/**
 * Defines an interface for SDK controller classes.
 */
interface SDKConnectorInterface {

  /**
   * Gets the organization.
   *
   * @return string
   *   The organization.
   */
  public function getOrganization() : string;

  /**
   * Returns the http client.
   *
   * @return \Apigee\Edge\HttpClient\ClientInterface
   *   The http client.
   */
  public function getClient() : ClientInterface;

  /**
   * Gets the requested controller object.
   *
   * Creates the requested controller object using the stored credentials
   * and the configured authentication method.
   *
   * @param string $entity_type
   *   Entity type.
   *
   * @return \Apigee\Edge\Controller\EntityCrudOperationsControllerInterface
   *   The controller object.
   */
  public function getControllerByEntity(string $entity_type) : EntityCrudOperationsControllerInterface;

  /**
   * Test connection with the Edge Management Server.
   *
   * @param \Drupal\apigee_edge\CredentialsInterface $credentials
   *   Credentials object to check connection with Edge,
   *   if NULL, then use the stored credentials.
   *
   * @throws \Exception
   */
  public function testConnection(CredentialsInterface $credentials = NULL);

}
