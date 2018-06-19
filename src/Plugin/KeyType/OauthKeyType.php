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

namespace Drupal\apigee_edge\Plugin\KeyType;

use Apigee\Edge\HttpClient\Plugin\Authentication\Oauth;
use Drupal\apigee_edge\OauthTokenStorage;
use Drupal\apigee_edge\Plugin\EdgeOauthKeyTypeInterface;
use Drupal\key\KeyInterface;
use Http\Message\Authentication;

/**
 * Key type for Apigee Edge OAuth credentials.
 *
 * @KeyType(
 *   id = "apigee_edge_oauth",
 *   label = @Translation("Apigee Edge OAuth"),
 *   description = @Translation("Key type to use for Apigee Edge OAuth credentials."),
 *   group = "apigee_edge",
 *   key_value = {
 *     "plugin" = "apigee_edge_oauth_input"
 *   },
 *   multivalue = {
 *     "enabled" = true,
 *     "fields" = {
 *       "endpoint" = {
 *         "label" = @Translation("Apigee Edge endpoint"),
 *         "required" = false
 *       },
 *       "organization" = {
 *         "label" = @Translation("Organization"),
 *         "required" = true
 *       },
 *       "username" = {
 *         "label" = @Translation("Username"),
 *         "required" = true
 *       },
 *       "password" = {
 *         "label" = @Translation("Password"),
 *         "required" = true
 *       },
 *       "authorization_server" = {
 *         "label" = @Translation("Authorization server"),
 *         "required" = false
 *       },
 *       "client_id" = {
 *         "label" = @Translation("Client ID"),
 *         "required" = false
 *       },
 *       "client_secret" = {
 *         "label" = @Translation("Client secret"),
 *         "required" = false
 *       }
 *     }
 *   }
 * )
 */
class OauthKeyType extends BasicAuthKeyType implements EdgeOauthKeyTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationServer(KeyInterface $key): ?string {
    return $key->getKeyValues()['authorization_server'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientId(KeyInterface $key): ?string {
    return $key->getKeyValues()['client_id'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientSecret(KeyInterface $key): ?string {
    return $key->getKeyValues()['client_secret'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationMethod(KeyInterface $key, KeyInterface $key_token = NULL): Authentication {
    return new Oauth($this->getUsername($key), $this->getPassword($key), new OauthTokenStorage($key_token), NULL, $this->getClientId($key), $this->getClientSecret($key), NULL, $this->getAuthorizationServer($key));
  }

}
