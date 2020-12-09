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

use Drupal\apigee_edge\Connector\GceServiceAccountAuthentication;
use Drupal\apigee_edge\Connector\HybridAuthentication;
use Drupal\apigee_edge\OauthAuthentication;
use Drupal\apigee_edge\Plugin\EdgeKeyTypeBase;
use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\KeyInterface;
use Http\Message\Authentication;
use Http\Message\Authentication\BasicAuth;

/**
 * Key type for Apigee Edge authentication credentials.
 *
 * @KeyType(
 *   id = "apigee_auth",
 *   label = @Translation("Apigee Edge Authentication"),
 *   description = @Translation("Key type to use for Apigee Edge authentication credentials."),
 *   group = "apigee_edge",
 *   key_value = {
 *     "plugin" = "apigee_auth_input"
 *   },
 *   multivalue = {
 *     "enabled" = true,
 *     "fields" = {
 *       "instance_type" = {
 *         "label" = @Translation("Instance type"),
 *         "required" = false
 *       },
 *       "auth_type" = {
 *         "label" = @Translation("Authentication type"),
 *         "required" = false
 *       },
 *       "organization" = {
 *         "label" = @Translation("Organization"),
 *         "required" = true
 *       },
 *       "username" = {
 *         "label" = @Translation("Username"),
 *         "required" = false
 *       },
 *       "password" = {
 *         "label" = @Translation("Password"),
 *         "required" = false
 *       },
 *       "endpoint" = {
 *         "label" = @Translation("Apigee Edge endpoint"),
 *         "required" = false
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
 *       },
 *       "account_json_key" = {
 *         "label" = @Translation("Account JSON key"),
 *         "required" = false
 *       },
 *       "gcp_hosted" = {
 *         "label" = @Translation("Use default service account if hosted on GCP"),
 *         "required" = false
 *       }
 *     }
 *   }
 * )
 */
class ApigeeAuthKeyType extends EdgeKeyTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function generateKeyValue(array $configuration) {
    return '[]';
  }

  /**
   * {@inheritdoc}
   */
  public function validateKeyValue(array $form, FormStateInterface $form_state, $key_value) {
    if (empty($key_value)) {
      return;
    }

    $value = $this->unserialize($key_value);
    if ($value === NULL) {
      $form_state->setError($form, $this->t('The key value does not contain valid JSON: @error', ['@error' => json_last_error_msg()]));
      return;
    }

    foreach ($this->getPluginDefinition()['multivalue']['fields'] as $id => $field) {
      if (isset($field['required']) && !$field['required']) {
        continue;
      }

      $error_element = $form['settings']['input_section']['key_input_settings'][$id] ?? $form;

      if (!isset($value[$id])) {
        $form_state->setError($error_element, $this->t('The key value is missing the field %field.', ['%field' => $field['label']->render()]));
      }
      elseif (empty($value[$id])) {
        $form_state->setError($error_element, $this->t('The key value field %field is empty.', ['%field' => $field['label']->render()]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationMethod(KeyInterface $key): Authentication {
    $values = $key->getKeyValues();
    if ($this->getInstanceType($key) === EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID) {
      if ($this->useGcpDefaultServiceAccount($key)) {
        return new GceServiceAccountAuthentication(\Drupal::service('apigee_edge.authentication.oauth_token_storage'));
      }
      else {
        $account_key = $this->getAccountKey($key);
        return new HybridAuthentication($account_key['client_email'], $account_key['private_key'], \Drupal::service('apigee_edge.authentication.oauth_token_storage'));
      }
    }
    elseif ($values['auth_type'] === EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH) {
      // Use Oauth authentication.
      return new OauthAuthentication($this->getUsername($key), $this->getPassword($key), \Drupal::service('apigee_edge.authentication.oauth_token_storage'), NULL, $this->getClientId($key), $this->getClientSecret($key), NULL, $this->getAuthorizationServer($key));
    }
    else {
      // Use basic authentication.
      return new BasicAuth($this->getUsername($key), $this->getPassword($key));
    }
  }

}
