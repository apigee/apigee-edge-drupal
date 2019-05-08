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
   * Gets the organization used by the API client.
   *
   * @return string
   *   The organization or an empty string if the Apigee Edge authentication
   *   key has not been saved yet.
   */
  public function getOrganization(): string;

  /**
   * Returns a pre-configured API client.
   *
   * @param null|\Http\Message\Authentication $authentication
   *   The authentication method, default is retrieved from the active key.
   * @param null|string $endpoint
   *   API endpoint, default is https://api.enterprise.apigee.com/v1.
   * @param array $options
   *   The API Client configuration options.
   *
   * @return \Apigee\Edge\ClientInterface
   *   The API client.
   *
   * @throws \Drupal\apigee_edge\Exception\AuthenticationKeyException
   *   If the API client could not be built, ex.: missing Apigee Edge
   *   authentication key.
   */
  public function getClient(?Authentication $authentication = NULL, ?string $endpoint = NULL, array $options = []): ClientInterface;

  /**
   * Test connection with the Edge Management Server.
   *
   * @param \Drupal\key\KeyInterface|null $key
   *   Key entity with Apigee Edge credentials or NULL if the saved key should
   *   be used.
   *
   * @throws \Drupal\apigee_edge\Exception\AuthenticationKeyException
   *   If the authentication fails with the saved- or provided key.
   */
  public function testConnection(KeyInterface $key = NULL): void;

}
