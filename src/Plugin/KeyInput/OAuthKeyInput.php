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
use Drupal\Core\Form\FormStateInterface;

/**
 * Apigee Edge OAuth credentials input text fields.
 *
 * Defines a key input that provides input text fields
 * and value preprocessors for Apigee Edge OAuth credentials.
 *
 * @KeyInput(
 *   id = "apigee_edge_oauth_input",
 *   label = @Translation("Apigee Edge OAuth credentials input fields."),
 *   description = @Translation("Provides input text fields for Apigee Edge OAuth credentials.")
 * )
 */
class OAuthKeyInput extends BasicAuthKeyInput {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $config = $this->configFactory->get('apigee_edge.client');

    /** @var \Drupal\key\Entity\Key $key */
    $key = $form_state->getFormObject()->getEntity();
    $values = Json::decode($form_state->get('key_value')['current']);

    $form['authorization_server'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authorization server'),
      '#description' => $this->t('The server issuing access tokens to the client. Defaults to: %url.', ['%url' => $config->get('default_authorization_server')]),
      '#required' => $key->getKeyProvider()->getPluginDefinition()['key_value']['required'],
      '#default_value' => $values['authorization_server'] ?? $config->get('default_authorization_server'),
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#description' => t('The client identifier issued to the client during the registration process. Defaults to: %client_id.', ['%client_id' => $config->get('default_client_id')]),
      '#required' => $key->getKeyProvider()->getPluginDefinition()['key_value']['required'],
      '#default_value' => $values['client_id'] ?? $config->get('default_client_id'),
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['client_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Client secret'),
      '#description' => t('A secret known only to the client and the authorization server. Leave empty to use the default "%client_secret" client secret.', ['%client_secret' => $config->get('default_client_secret')]),
      '#required' => FALSE,
      '#attributes' => ['autocomplete' => 'off'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function processSubmittedKeyValue(FormStateInterface $form_state) {
    $input_values = $form_state->getValues();
    $key_values = Json::decode($input_values['key_value']);

    $key_values['authorization_server'] = $input_values['authorization_server'];
    $key_values['client_id'] = $input_values['client_id'];
    $key_values['client_secret'] = $input_values['client_secret'];

    $input_values['key_value'] = Json::encode($key_values);

    // Remove field values from settings.
    unset($input_values['authorization_server'], $input_values['client_id'], $input_values['client_secret']);
    $form_state->setValues($input_values);
    return parent::processSubmittedKeyValue($form_state);
  }

}
