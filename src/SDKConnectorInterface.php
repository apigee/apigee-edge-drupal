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

use Apigee\Edge\ClientInterface;
use Drupal\key\KeyInterface;
use Http\Message\Authentication;

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
  public function getOrganization(): string;

  /**
   * Returns the http client.
   *
   * @return \Apigee\Edge\ClientInterface
   *   The http client.
   */
  public function getClient(): ClientInterface;

  /**
   * Test connection with the Edge Management Server.
   *
   * @param \Drupal\key\KeyInterface|null $key
   *   Key entity to check connection with Edge,
   *   if NULL, then use the stored key.
   *
   * @throws \Exception
   */
  public function testConnection(KeyInterface $key = NULL);

  /**
   * Returns a pre-configured API client with the provided credentials.
   *
   * @param \Http\Message\Authentication $authentication
   *   Authentication.
   * @param null|string $endpoint
   *   API endpoint, default is https://api.enterprise.apigee.com/v1.
   * @param array $options
   *   Client configuration option.
   *
   * @return \Apigee\Edge\ClientInterface
   *   Configured API client.
   */
  public function buildClient(Authentication $authentication, ?string $endpoint = NULL, array $options = []): ClientInterface;

}
