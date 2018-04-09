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

namespace Drupal\apigee_edge\Plugin\KeyInput;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\Plugin\KeyInputBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Apigee Edge basic authentication credentials input text fields.
 *
 * Defines a key input that provides input text fields
 * and value preprocessors for Apigee Edge basic authentication credentials.
 *
 * @KeyInput(
 *   id = "apigee_edge_basic_auth_input",
 *   label = @Translation("Apigee Edge basic authentication credentials input fields."),
 *   description = @Translation("Provides input text fields for Apigee Edge basic authentication credentials.")
 * )
 */
class BasicAuthKeyInput extends KeyInputBase {

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
    $config = $this->configFactory->get('apigee_edge.client');

    /** @var \Drupal\key\Entity\Key $key */
    $key = $form_state->getFormObject()->getEntity();
    $values = Json::decode($form_state->get('key_value')['current']);

    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Apigee Edge endpoint'),
      '#description' => $this->t('Apigee Edge endpoint where the API calls are being sent. Defaults to the enterprise endpoint: %url.', ['%url' => $config->get('default_endpoint')]),
      '#required' => $key->getKeyProvider()->getPluginDefinition()['key_value']['required'],
      '#default_value' => $values['endpoint'] ?? $config->get('default_endpoint'),
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['organization'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization'),
      '#description' => $this->t('Name of the organization on Edge. Changing this value could make your site stop working.'),
      '#required' => $key->getKeyProvider()->getPluginDefinition()['key_value']['required'],
      '#default_value' => $values['organization'],
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['username'] = [
      '#type' => 'email',
      '#title' => $this->t('Username'),
      '#description' => $this->t("Organization user's email address that is used for authenticating with the endpoint."),
      '#required' => $key->getKeyProvider()->getPluginDefinition()['key_value']['required'],
      '#default_value' => $values['username'],
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => t("Organization user's password that is used for authenticating with the endpoint."),
      '#required' => $key->getKeyProvider()->getPluginDefinition()['key_value']['required'],
      '#attributes' => ['autocomplete' => 'off'],
    ];

    $form['key_value'] = [
      '#type' => 'value',
      '#value' => $form_state->get('key_value')['current'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function processSubmittedKeyValue(FormStateInterface $form_state) {
    $input_values = $form_state->getValues();
    $input_values['key_value'] = Json::encode([
      'endpoint' => $input_values['endpoint'],
      'organization' => $input_values['organization'],
      'username' => $input_values['username'],
      'password' => $input_values['password'],
    ]);

    // Remove field values from settings.
    unset($input_values['endpoint'], $input_values['organization'], $input_values['username'], $input_values['password']);
    $form_state->setValues($input_values);
    return parent::processSubmittedKeyValue($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function processExistingKeyValue($key_value) {
    return $key_value;
  }

}
