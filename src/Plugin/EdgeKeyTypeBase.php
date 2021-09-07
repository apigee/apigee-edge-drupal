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

use Apigee\Edge\Client;
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
    if ($this->getInstanceType($key) === EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID) {
      if ($this->useGcpDefaultServiceAccount($key)) {
        return EdgeKeyTypeInterface::EDGE_AUTH_TYPE_DEFAULT_GCE_SERVICE_ACCOUNT;
      }
      else {
        return EdgeKeyTypeInterface::EDGE_AUTH_TYPE_JWT;
      }
    }
    if (!isset($key->getKeyValues()['auth_type'])) {
      throw new AuthenticationKeyValueMalformedException('auth_type');
    }
    return $key->getKeyValues()['auth_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint(KeyInterface $key): string {
    if ($this->getInstanceType($key) === EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID) {
      return ClientInterface::APIGEE_ON_GCP_ENDPOINT;
    }
    elseif ($this->getInstanceType($key) === EdgeKeyTypeInterface::INSTANCE_TYPE_PUBLIC) {
      return Client::EDGE_ENDPOINT;
    }
    return $key->getKeyValues()['endpoint'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpointType(KeyInterface $key): string {
    if ($this->getInstanceType($key) === EdgeKeyTypeInterface::INSTANCE_TYPE_PUBLIC) {
      /* @phpstan-ignore-next-line */
      return EdgeKeyTypeInterface::EDGE_ENDPOINT_TYPE_DEFAULT;
    }

    /* @phpstan-ignore-next-line */
    return EdgeKeyTypeInterface::EDGE_ENDPOINT_TYPE_CUSTOM;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceType(KeyInterface $key): string {
    $key_values = $key->getKeyValues();
    if (isset($key_values['instance_type'])) {
      return $key_values['instance_type'];
    }

    // Backwards compatibility, before Hybrid support.
    if (empty($key_values['endpoint']) || $key_values['endpoint'] === ClientInterface::EDGE_ENDPOINT) {
      return EdgeKeyTypeInterface::INSTANCE_TYPE_PUBLIC;
    }

    return EdgeKeyTypeInterface::INSTANCE_TYPE_PRIVATE;
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
  public function getAuthorizationServer(KeyInterface $key): string {
    return $key->getKeyValues()['authorization_server'] ?? Oauth::DEFAULT_AUTHORIZATION_SERVER;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientId(KeyInterface $key): string {
    return $key->getKeyValues()['client_id'] ?? Oauth::DEFAULT_CLIENT_ID;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientSecret(KeyInterface $key): string {
    return $key->getKeyValues()['client_secret'] ?? Oauth::DEFAULT_CLIENT_SECRET;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountKey(KeyInterface $key): array {
    $value = $key->getKeyValues()['account_json_key'] ?? '';
    $json = json_decode($value, TRUE);
    if (empty($json['private_key']) || empty($json['client_email'])) {
      throw new AuthenticationKeyValueMalformedException('account_json_key');
    }
    return $json;
  }

  /**
   * {@inheritdoc}
   */
  public function useGcpDefaultServiceAccount(KeyInterface $key): bool {
    return !empty($key->getKeyValues()['gcp_hosted']);
  }

}
