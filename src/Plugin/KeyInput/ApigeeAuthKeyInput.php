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

use Apigee\Edge\HttpClient\Plugin\Authentication\Oauth;
use Apigee\Edge\ClientInterface;
use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\Plugin\KeyInputBase;

/**
 * Apigee Edge authentication credentials input text fields.
 *
 * Defines a key input that provides input text fields and value preprocessors
 * for Apigee Edge authentication credentials.
 *
 * @KeyInput(
 *   id = "apigee_auth_input",
 *   label = @Translation("Apigee Edge authentication credentials input fields."),
 *   description = @Translation("Provides input text fields for Apigee Edge authentication credentials.")
 * )
 */
class ApigeeAuthKeyInput extends KeyInputBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // When AJAX rebuilds the the form (f.e.: the "Send request" button) the
    // submitted data is only available in $form_state->getUserInput() and not
    // in $form_state->getValues(). Key is not prepared to handle this out of
    // the box this is the reason why we have to manually process the user
    // input and retrieve the submitted values here.
    $key_value = $form_state->get('key_value')['current'];
    // Either null or an empty string.
    if (empty($key_value)) {
      // When "Test connection" reloads the page they are not yet processed.
      // @see \Drupal\key\Form\KeyFormBase::createPluginFormState()
      $key_input_plugin_form_state = clone $form_state;
      $key_input_plugin_form_state->setValues($form_state->getUserInput()['key_input_settings']);
      // @see \Drupal\key\Form\KeyFormBase::validateForm()
      $key_input_processed_values = $form_state->getFormObject()->getEntity()->getKeyInput()->processSubmittedKeyValue($key_input_plugin_form_state);
      $key_value = $key_input_processed_values['processed_submitted'];
    }

    // Could be an empty array.
    $values = Json::decode($key_value);

    $form['auth_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Authentication type'),
      '#description' => $this->t('Select the authentication method to use.'),
      '#required' => TRUE,
      '#options' => [
        EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH => $this->t('OAuth'),
        EdgeKeyTypeInterface::EDGE_AUTH_TYPE_BASIC => $this->t('HTTP basic'),
      ],
      '#default_value' => $values['auth_type'] ?? EdgeKeyTypeInterface::EDGE_AUTH_TYPE_BASIC,
    ];

    $form['organization'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization'),
      '#description' => $this->t('Name of the organization on Apigee Edge. Changing this value could make your site stop working.'),
      '#required' => TRUE,
      '#default_value' => $values['organization'] ?? '',
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['username'] = [
      '#type' => 'email',
      '#title' => $this->t('Username'),
      '#description' => $this->t("Organization user's email address that is used for authenticating with the endpoint."),
      '#required' => TRUE,
      '#default_value' => $values['username'] ?? '',
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t("Organization user's password that is used for authenticating with the endpoint."),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off',
        // Password field should not forget the submitted value.
        'value' => $values['password'] ?? '',
      ],
    ];
    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Apigee Edge endpoint'),
      '#description' => $this->t('Apigee Edge endpoint where the API calls are being sent. Leave empty to use the default %endpoint endpoint.', [
        '%endpoint' => ClientInterface::DEFAULT_ENDPOINT,
      ]),
      '#default_value' => $values['endpoint'] ?? '',
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['authorization_server'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authorization server'),
      '#description' => $this->t('The server issuing access tokens to the client. Leave empty to use the default %authorization_server authorization server.', [
        '%authorization_server' => Oauth::DEFAULT_AUTHORIZATION_SERVER,
      ]),
      '#default_value' => $values['authorization_server'] ?? '',
      '#attributes' => ['autocomplete' => 'off'],
      '#states' => [
        'visible' => [
          ':input[name="key_input_settings[auth_type]"]' => ['value' => EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH],
        ],
      ],
    ];
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#description' => $this->t('The client identifier issued to the client during the registration process. Leave empty to use the default %client_id client ID.', [
        '%client_id' => Oauth::DEFAULT_CLIENT_ID,
      ]),
      '#default_value' => $values['client_id'] ?? '',
      '#attributes' => ['autocomplete' => 'off'],
      '#states' => [
        'visible' => [
          ':input[name="key_input_settings[auth_type]"]' => ['value' => EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH],
        ],
      ],
    ];
    $form['client_secret'] = [
      '#type' => 'password',
      '#title' => $this->t('Client secret'),
      '#description' => $this->t('A secret known only to the client and the authorization server. Leave empty to use the default %client_secret client secret.', [
        '%client_secret' => Oauth::DEFAULT_CLIENT_SECRET,
      ]),
      '#default_value' => $values['client_secret'] ?? '',
      '#attributes' => ['autocomplete' => 'off'],
      '#states' => [
        'visible' => [
          ':input[name="key_input_settings[auth_type]"]' => ['value' => EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH],
        ],
      ],
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
    // Get input values.
    $input_values = $form_state->getValues();
    // Remove `key_value` so it doesn't get double encoded.
    unset($input_values['key_value']);
    // Reset values to just `key_value`.
    $form_state->setValues(['key_value' => Json::encode(array_filter($input_values))]);
    return parent::processSubmittedKeyValue($form_state);
  }

}
