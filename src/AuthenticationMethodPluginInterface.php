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

use Drupal\Component\Plugin\PluginInspectionInterface;
use Http\Message\Authentication as AuthenticationInterface;

/**
 * Defines an interface for authentication method plugins.
 */
interface AuthenticationMethodPluginInterface extends PluginInspectionInterface {

  /**
   * Returns the ID of the authentication method plugin.
   *
   * @return string
   *   The ID of the authentication method plugin.
   */
  public function getId() : string;

  /**
   * Returns the name of the authentication method plugin.
   *
   * @return string
   *   The name of the authentication method plugin.
   */
  public function getName() : string;

  /**
   * Creates an authentication object.
   *
   * @param \Drupal\apigee_edge\CredentialsInterface $credentials
   *   An object that implements \Drupal\apigee_edge\CredentialsInterface
   *   which contains the API credentials.
   *
   * @return \Http\Message\Authentication
   *   An object that implements \Http\Message\Authentication.
   */
  public function createAuthenticationObject(CredentialsInterface $credentials) : AuthenticationInterface;

}
