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

use Apigee\Edge\ClientInterface;
use Apigee\Edge\HttpClient\Plugin\Authentication\Oauth;
use Drupal\apigee_edge\Exception\AuthenticationKeyValueMalformedException;
use Drupal\Component\Serialization\Json;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyTypeBase;

/**
 * Defines a base class for Apigee Edge Key Type plugins.
 */
abstract class EdgeKeyTypeBase extends KeyTypeBase implements EdgeKeyTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function serialize(array $array) {
    return Json::encode($array);
  }

  /**
   * {@inheritdoc}
   */
  public function unserialize($value) {
    return Json::decode($value);
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationType(KeyInterface $key): string {
    if (!isset($key->getKeyValues()['auth_type'])) {
      throw new AuthenticationKeyValueMalformedException('auth_type');
    }
    return $key->getKeyValues()['auth_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint(KeyInterface $key, bool $return_default = FALSE): ?string {
    if (isset($key->getKeyValues()['endpoint'])) {
      return $key->getKeyValues()['endpoint'];
    }

    return $return_default ? ClientInterface::DEFAULT_ENDPOINT : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(KeyInterface $key): string {
    if (!isset($key->getKeyValues()['organization'])) {
      throw new AuthenticationKeyValueMalformedException('organization');
    }
    return $key->getKeyValues()['organization'];
  }

  /**
   * {@inheritdoc}
   */
  public function getUsername(KeyInterface $key): string {
    if (!isset($key->getKeyValues()['username'])) {
      throw new AuthenticationKeyValueMalformedException('username');
    }
    return $key->getKeyValues()['username'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPassword(KeyInterface $key): string {
    if (!isset($key->getKeyValues()['password'])) {
      throw new AuthenticationKeyValueMalformedException('password');
    }
    return $key->getKeyValues()['password'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationServer(KeyInterface $key, bool $return_default = FALSE): ?string {
    if (isset($key->getKeyValues()['authorization_server'])) {
      return $key->getKeyValues()['authorization_server'];
    }

    return $return_default ? Oauth::DEFAULT_AUTHORIZATION_SERVER : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientId(KeyInterface $key, bool $return_default = FALSE): ?string {
    if (isset($key->getKeyValues()['client_id'])) {
      return $key->getKeyValues()['client_id'];
    }

    return $return_default ? Oauth::DEFAULT_CLIENT_ID : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientSecret(KeyInterface $key, bool $return_default = FALSE): ?string {
    if (isset($key->getKeyValues()['client_secret'])) {
      return $key->getKeyValues()['client_secret'];
    }

    return $return_default ? Oauth::DEFAULT_CLIENT_SECRET : NULL;
  }

}
