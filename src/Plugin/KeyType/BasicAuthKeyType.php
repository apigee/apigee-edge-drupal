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

use Drupal\apigee_edge\Plugin\EdgeKeyTypeBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\KeyInterface;
use Http\Message\Authentication;
use Http\Message\Authentication\BasicAuth;

/**
 * Key type for Apigee Edge basic authentication credentials.
 *
 * @KeyType(
 *   id = "apigee_edge_basic_auth",
 *   label = @Translation("Apigee Edge Basic Authentication"),
 *   description = @Translation("Key type to use for Apigee Edge basic authentication credentials."),
 *   group = "apigee_edge",
 *   key_value = {
 *     "plugin" = "apigee_edge_basic_auth_input"
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
 *       }
 *     }
 *   }
 * )
 */
class BasicAuthKeyType extends EdgeKeyTypeBase {

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
      if (isset($field['required']) && $field['required'] === FALSE) {
        continue;
      }

      $error_element = $form['settings']['input_section']['key_input_settings'][$id] ?? $form;

      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $field */
      if (!isset($value[$id])) {
        $form_state->setError($error_element, $this->t('The key value is missing the field %field.', ['%field' => $field->render()]));
      }
      elseif (empty($value[$id])) {
        $form_state->setError($error_element, $this->t('The key value field %field is empty.', ['%field' => $field->render()]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationMethod(KeyInterface $key, KeyInterface $key_token = NULL): Authentication {
    return new BasicAuth($this->getUsername($key), $this->getPassword($key));
  }

}
