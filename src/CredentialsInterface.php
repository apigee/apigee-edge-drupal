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

use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\key\KeyInterface;
use Http\Message\Authentication;

/**
 * Defines an interface for credentials classes.
 *
 * @todo: move to \Drupal\apigee_edge\Connector namespace.
 */
interface CredentialsInterface {

  /**
   * Gets the authentication object which instantiated by the key type.
   *
   * @return \Http\Message\Authentication
   *   The authentication object.
   */
  public function getAuthentication(): Authentication;

  /**
   * Gets the key entity which stores the API credentials.
   *
   * @return \Drupal\key\KeyInterface
   *   The key entity which stores the API credentials.
   */
  public function getKey(): KeyInterface;

  /**
   * Gets the key type of the key entity.
   *
   * @return \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface
   *   The key type of the key entity.
   */
  public function getKeyType(): EdgeKeyTypeInterface;

}
