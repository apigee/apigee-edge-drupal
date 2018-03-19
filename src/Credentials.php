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
 * The API credentials.
 */
class Credentials implements CredentialsInterface {

  /**
   * The authentication object.
   *
   * @var \Http\Message\Authentication
   */
  protected $authentication;

  /**
   * The Edge API endpoint.
   *
   * @var string
   */
  protected $endpoint;

  /**
   * The name of the organization.
   *
   * @var string
   */
  protected $organization;

  /**
   * The API username.
   *
   * @var string
   */
  protected $username;

  /**
   * The API password.
   *
   * @var string
   */
  protected $password;

  /**
   * Credentials constructor.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   *
   * @throws \InvalidArgumentException
   *   An InvalidArgumentException is thrown if the key type
   *   does not implement EdgeKeyTypeInterface.
   */
  public function __construct(KeyInterface $key) {
    if (!(($key_type = $key->getKeyType()) instanceof EdgeKeyTypeInterface)) {
      throw new \InvalidArgumentException("Type of {$key->id()} key does not implement EdgeKeyTypeInterface.");
    }

    /** @var \Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface $key_type */
    $this->authentication = $key_type->getAuthenticationMethod($key);
    $this->endpoint = $key_type->getEndpoint($key);
    $this->organization = $key_type->getOrganization($key);
    $this->username = $key_type->getUsername($key);
    $this->password = $key_type->getPassword($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthentication(): Authentication {
    return $this->authentication;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint(): string {
    return $this->endpoint;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(): string {
    return $this->organization;
  }

  /**
   * {@inheritdoc}
   */
  public function getUsername(): string {
    return $this->username;
  }

  /**
   * {@inheritdoc}
   */
  public function getPassword(): string {
    return $this->password;
  }

}
