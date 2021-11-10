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
   * Apigee instance on public cloud.
   *
   * @var string
   */
  public const INSTANCE_TYPE_PUBLIC = 'public';

  /**
   * Apigee instance on private cloud.
   *
   * @var string
   */
  public const INSTANCE_TYPE_PRIVATE = 'private';

  /**
   * Apigee instance on hybrid cloud.
   *
   * @var string
   */
  public const INSTANCE_TYPE_HYBRID = 'hybrid';
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
   * ID of the JWT authentication method.
   *
   * @var string
   */
  const EDGE_AUTH_TYPE_JWT = 'jwt';

  const EDGE_AUTH_TYPE_DEFAULT_GCE_SERVICE_ACCOUNT = 'gce-service-account';

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
   * Gets the instance type (public, private or hybrid).
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return string
   *   The instance type, either `public`, `private` or `hybrid`.
   */
  public function getInstanceType(KeyInterface $key): string;

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

  /**
   * Return the JSON account key decoded as an array.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return array
   *   The account key as an array.
   */
  public function getAccountKey(KeyInterface $key): array;

  /**
   * Return if you should use the Default Service account.
   *
   * This applies to portals hosted on Google Compute Engine.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @return bool
   *   The account key as an array.
   */
  public function useGcpDefaultServiceAccount(KeyInterface $key): bool;

}
