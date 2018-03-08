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
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\Plugin\KeyProviderBase;
use Drupal\key\Plugin\KeyPluginFormInterface;
use Drupal\key\Plugin\KeyProviderSettableValueInterface;
use Drupal\key\KeyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Storing Apigee Edge basic auth credentials in environment variables.
 *
 * @KeyProvider(
 *   id = "apigee_edge_basic_auth_env_variables",
 *   label = @Translation("Apigee Edge Basic Auth: Environment Variables"),
 *   description = @Translation("Stores Apigee Edge basic auth credentials in the following environment variables: APIGEE_EDGE_ENDPOINT, APIGEE_EDGE_ORGANIZATION, APIGEE_EDGE_USERNAME, APIGEE_EDGE_PASSWORD"),
 *   storage_method = "apigee_edge",
 *   key_value = {
 *     "accepted" = FALSE,
 *     "required" = FALSE
 *   }
 * )
 */
class BasicAuthEnvVariablesKeyProvider extends KeyProviderBase implements KeyPluginFormInterface, KeyProviderSettableValueInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $missing_env_variables = [];

    if (!getenv('APIGEE_EDGE_ORGANIZATION')) {
      $missing_env_variables[] = 'APIGEE_EDGE_ORGANIZATION';
    }
    if (!getenv('APIGEE_EDGE_USERNAME')) {
      $missing_env_variables[] = 'APIGEE_EDGE_USERNAME';
    }
    if (!getenv('APIGEE_EDGE_PASSWORD')) {
      $missing_env_variables[] = 'APIGEE_EDGE_PASSWORD';
    }

    return $this->t('The following environment variables are not set: @missing_env_variables.', [
      '@missing_env_variables' => implode(', ', $missing_env_variables),
    ]);
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
    $config = $this->configFactory->get('apigee_edge.authentication');

    return Json::encode([
      'endpoint' => getenv('APIGEE_EDGE_ENDPOINT') ?: $config->get('default_endpoint'),
      'organization' => getenv('APIGEE_EDGE_ORGANIZATION'),
      'username' => getenv('APIGEE_EDGE_USERNAME'),
      'password' => getenv('APIGEE_EDGE_PASSWORD'),
    ]);
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

}
