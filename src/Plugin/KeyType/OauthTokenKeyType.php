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

use Drupal\apigee_edge\Plugin\EdgeOauthTokenKeyTypeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyTypeBase;

/**
 * Key type for Apigee Edge OAuth tokens.
 *
 * @KeyType(
 *   id = "apigee_edge_oauth_token",
 *   label = @Translation("Apigee Edge OAuth token"),
 *   description = @Translation("Key type to use for Apigee Edge OAuth tokens."),
 *   group = "apigee_edge",
 *   key_value = {
 *     "plugin" = "none"
 *   },
 *   multivalue = {
 *     "enabled" = true,
 *     "fields" = {
 *       "access_token" = {
 *         "label" = @Translation("Access token"),
 *         "required" = false
 *       },
 *       "refresh_token" = {
 *         "label" = @Translation("Refresh token"),
 *         "required" = false
 *       },
 *       "scope" = {
 *         "label" = @Translation("Scope"),
 *         "required" = false
 *       },
 *       "token_type" = {
 *         "label" = @Translation("Token type"),
 *         "required" = false
 *       },
 *       "expires_in" = {
 *         "label" = @Translation("Expires in"),
 *         "required" = false
 *       },
 *       "expires" = {
 *         "label" = @Translation("Expires"),
 *         "required" = false
 *       }
 *     }
 *   }
 * )
 */
class OauthTokenKeyType extends KeyTypeBase implements EdgeOauthTokenKeyTypeInterface {

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
  public static function generateKeyValue(array $configuration) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateKeyValue(array $form, FormStateInterface $form_state, $key_value) {

  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(KeyInterface $key): ?string {
    return $key->getKeyValues()['access_token'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRefreshToken(KeyInterface $key): ?string {
    return $key->getKeyValues()['refresh_token'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getScope(KeyInterface $key): ?string {
    return $key->getKeyValues()['scope'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenType(KeyInterface $key): ?string {
    return $key->getKeyValues()['token_type'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpiresIn(KeyInterface $key): ?int {
    return $key->getKeyValues()['expires_in'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpires(KeyInterface $key): ?int {
    return $key->getKeyValues()['expires'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown in case of an error during the entity save process.
   */
  public function resetExpires(KeyInterface $key) {
    $key_value = $this->unserialize($key->getKeyValue());
    $key_value['expires'] = 0;
    $key->setKeyValue($this->serialize($key_value));
    $key->save();
  }

}
