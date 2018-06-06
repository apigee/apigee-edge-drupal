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

namespace Drupal\apigee_edge\Plugin\KeyProvider;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyPluginFormInterface;
use Drupal\key\Plugin\KeyProviderBase;
use Drupal\key\Plugin\KeyProviderSettableValueInterface;

/**
 * Stores Apigee Edge authentication credentials in environment variables.
 *
 * @KeyProvider(
 *   id = "apigee_edge_environment_variables",
 *   label = @Translation("Apigee Edge: Environment Variables"),
 *   description = @Translation("Stores Apigee Edge authentication credentials in the following environment variables:"),
 *   storage_method = "apigee_edge",
 *   key_value = {
 *     "accepted" = FALSE,
 *     "required" = FALSE
 *   }
 * )
 */
class EnvironmentVariablesKeyProvider extends KeyProviderBase implements KeyPluginFormInterface, KeyProviderSettableValueInterface {

  /**
   * The selected key type.
   *
   * @var \Drupal\key\Plugin\KeyTypeInterface
   */
  protected $keyType;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->keyType = $form_state->getFormObject()->getEntity()->getKeyType();
    $form['environment_variables'] = [
      '#markup' => implode(', ', $this->getEnvironmentVariables()),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $missing_env_variables = [];
    foreach ($this->getEnvironmentVariables(TRUE) as $variable) {
      if (!getenv($variable)) {
        $missing_env_variables[] = $variable;
      }
    }

    if (!empty($missing_env_variables)) {
      $form_state->setError($form, t('The following environment variables are not set: @missing_env_variables.', [
        '@missing_env_variables' => implode(', ', $missing_env_variables),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValue(KeyInterface $key) {
    $this->keyType = $key->getKeyType();

    $key_value = [];
    foreach ($this->getEnvironmentVariables() as $id => $variable) {
      if (getenv($variable)) {
        $key_value[$id] = getenv($variable);
      }
    }

    return Json::encode($key_value);
  }

  /**
   * {@inheritdoc}
   */
  public function setKeyValue(KeyInterface $key, $key_value) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteKeyValue(KeyInterface $key) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function obscureKeyValue($key_value, array $options = []) {
    return $key_value;
  }

  /**
   * Returns an array containing the environment variables by key type.
   *
   * @param bool $required
   *   Returns only the required environment variables.
   *
   * @return array
   *   The environment variables.
   */
  protected function getEnvironmentVariables(bool $required = FALSE): array {
    $environment_variables = [];
    foreach ($this->keyType->getPluginDefinition()['multivalue']['fields'] as $id => $field) {
      if ($required && isset($field['required']) && $field['required'] === FALSE) {
        continue;
      }
      $environment_variables[$id] = 'APIGEE_EDGE_' . strtoupper($id);
    }

    return $environment_variables;
  }

}
