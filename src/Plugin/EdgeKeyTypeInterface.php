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
use Drupal\key\Plugin\KeyTypeMultivalueInterface;

/**
 * Defines an interface for Apigee Edge Key Type plugins.
 */
interface EdgeKeyTypeInterface extends KeyTypeMultivalueInterface, KeyTypeAuthenticationMethodInterface {

  /**
   * ID of the basic authentication method.
   *
   * @var string
   */
  const EDGE_AUTH_TYPE_BASIC = 'basic';

  /**
   * ID of the OAuth authentication method.
   *
   * @var string
   */
  const EDGE_AUTH_TYPE_OAUTH = 'oauth';

  /**
   * Gets the authentication type.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string
   *   The Authentication type.
   */
  public function getAuthenticationType(KeyInterface $key): string;

  /**
   * Gets the API endpoint.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string
   *   The API endpoint.
   */
  public function getEndpoint(KeyInterface $key): string;

  /**
   * Gets the API organization.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string
   *   The API organization.
   */
  public function getOrganization(KeyInterface $key): string;

  /**
   * Gets the API username.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string
   *   The API username.
   */
  public function getUsername(KeyInterface $key): string;

  /**
   * Gets the API password.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string
   *   The API password.
   */
  public function getPassword(KeyInterface $key): string;

  /**
   * Gets the authorization server.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string
   *   The authorization server.
   */
  public function getAuthorizationServer(KeyInterface $key): string;

  /**
   * Gets the client ID.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string
   *   The client ID.
   */
  public function getClientId(KeyInterface $key): string;

  /**
   * Gets the client secret.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string
   *   The client secret.
   */
  public function getClientSecret(KeyInterface $key): string;

}
