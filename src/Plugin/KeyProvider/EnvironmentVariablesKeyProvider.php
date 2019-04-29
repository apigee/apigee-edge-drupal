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

use Drupal\apigee_edge\Exception\KeyProviderRequirementsException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyPluginFormInterface;

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
class EnvironmentVariablesKeyProvider extends KeyProviderRequirementsBase implements KeyPluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['environment_variables'] = [
      '#markup' => implode(', ', $this->getEnvironmentVariables($form_state->getFormObject()->getEntity())),
    ];

    return $form;
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
  public function realGetKeyValue(KeyInterface $key) {
    $key_value = [];
    foreach ($this->getEnvironmentVariables($key) as $id => $variable) {
      if (getenv($variable)) {
        $key_value[$id] = getenv($variable);
      }
    }

    return Json::encode((object) $key_value);
  }

  /**
   * {@inheritdoc}
   */
  public static function obscureKeyValue($key_value, array $options = []) {
    return $key_value;
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(KeyInterface $key): void {
    $missing_env_variables = [];
    foreach ($this->getEnvironmentVariables($key, TRUE) as $variable) {
      if (!getenv($variable)) {
        $missing_env_variables[] = $variable;
      }
    }

    if (!empty($missing_env_variables)) {
      $missing_env_variables_to_string = implode(', ', $missing_env_variables);
      throw new KeyProviderRequirementsException('The following environment variables are not set: ' . $missing_env_variables_to_string, $this->t('The following environment variables are not set: @missing_env_variables.', [
        '@missing_env_variables' => $missing_env_variables_to_string,
      ]));
    }
  }

  /**
   * Returns an array containing the environment variables by key type.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The key entity.
   * @param bool $required
   *   Returns only the required environment variables.
   *
   * @return array
   *   The environment variables.
   */
  protected function getEnvironmentVariables(KeyInterface $key, bool $required = FALSE): array {
    $environment_variables = [];
    foreach ($key->getKeyType()->getPluginDefinition()['multivalue']['fields'] as $id => $field) {
      if ($required && isset($field['required']) && !$field['required']) {
        continue;
      }
      $environment_variables[$id] = 'APIGEE_EDGE_' . strtoupper($id);
    }

    return $environment_variables;
  }

}
