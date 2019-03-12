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
use Drupal\apigee_edge\Plugin\KeyProviderRequirementsInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyPluginFormInterface;
use Drupal\key\Plugin\KeyProviderBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class EnvironmentVariablesKeyProvider extends KeyProviderBase implements KeyPluginFormInterface, KeyProviderRequirementsInterface {

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * EnvironmentVariablesKeyProvider constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.apigee_edge')
    );
  }

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
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->checkRequirements($form_state->getFormObject()->getEntity());
    }
    catch (KeyProviderRequirementsException $exception) {
      $form_state->setError($form, $exception->getTranslatableMarkupMessage());
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
    // Throwing an exception would be better than returning NULL but the key
    // module's design does not allow this.
    // Related issue: https://www.drupal.org/project/key/issues/3038212
    try {
      $this->checkRequirements($key);
    }
    catch (KeyProviderRequirementsException $exception) {
      $context = [
        '@message' => (string) $exception,
      ];
      $context += Error::decodeException($exception);
      $this->getLogger()->error('Could not retrieve Apigee Edge authentication key value from the environment variables: @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
      return NULL;
    }
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

  /**
   * Gets the logger service.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The logger service.
   */
  protected function getLogger(): LoggerChannelInterface {
    // This fallback is needed when the plugin instance is serialized and the
    // property is null.
    return $this->logger ?? \Drupal::service('logger.channel.apigee_edge');
  }

}
