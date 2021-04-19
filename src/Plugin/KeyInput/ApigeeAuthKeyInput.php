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
use Drupal\apigee_edge\Connector\GceServiceAccountAuthentication;
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
    $values = $this->getFormDefaultValues($form_state);

    if (!empty($values['auth_type']) && $values['auth_type'] == EdgeKeyTypeInterface::EDGE_AUTH_TYPE_BASIC) {
      $this->messenger()->addWarning($this->t('HTTP basic authentication will be deprecated. Please choose another authentication method.'));
    }

    $state_for_public = [
      ':input[name="key_input_settings[instance_type]"]' => ['value' => EdgeKeyTypeInterface::INSTANCE_TYPE_PUBLIC],
    ];
    $state_for_private = [
      ':input[name="key_input_settings[instance_type]"]' => ['value' => EdgeKeyTypeInterface::INSTANCE_TYPE_PRIVATE],
    ];
    $state_for_hybrid = [
      ':input[name="key_input_settings[instance_type]"]' => ['value' => EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID],
    ];

    $form['instance_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Apigee instance type'),
      '#description' => $this->t('Select the Apigee instance type you are connecting to. More information can be found in the <a href="@link" target="_blank">Apigee documentation</a>.', [
        '@link' => 'https://www.drupal.org/docs/8/modules/apigee-edge/configure-the-connection-to-apigee-edge',
      ]),
      '#required' => TRUE,
      '#options' => [
        EdgeKeyTypeInterface::INSTANCE_TYPE_PUBLIC => $this->t('Apigee Edge (Endpoint: https://api.enterprise.apigee.com/v1)'),
        EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID => $this->t('Apigee X (Endpoint: https://apigee.googleapis.com/v1)'),
        EdgeKeyTypeInterface::INSTANCE_TYPE_PRIVATE => $this->t('Private cloud (Custom endpoint)'),
      ],
      '#default_value' => $values['instance_type'] ?? 'public',
    ];
    $form['auth_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Authentication type'),
      '#description' => $this->t('Select the authentication method to use.'),
      '#required' => TRUE,
      '#options' => [
        EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH => $this->t('OAuth'),
        EdgeKeyTypeInterface::EDGE_AUTH_TYPE_BASIC => $this->t('HTTP basic (deprecated)'),
      ],
      '#default_value' => $values['auth_type'] ?? EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH,
      '#states' => [
        'visible' => [$state_for_public, $state_for_private],
        'required' => [$state_for_public, $state_for_private],
      ],
    ];
    $form['organization'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization'),
      '#description' => $this->t('Name of the organization on Apigee Edge. Changing this value could make your site stop working.'),
      '#default_value' => $values['organization'] ?? '',
      '#required' => TRUE,
      '#attributes' => ['autocomplete' => 'off'],
      '#prefix' => '<div id="edit-organization-field">',
      '#suffix' => '</div>',
    ];
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#description' => $this->t("Apigee user's email address or identity provider username that is used for authenticating with the endpoint."),
      '#default_value' => $values['username'] ?? '',
      '#attributes' => ['autocomplete' => 'off'],
      '#states' => [
        'visible' => [$state_for_public, $state_for_private],
        'required' => [$state_for_public, $state_for_private],
      ],
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t("Organization user's password that is used for authenticating with the endpoint.") .
      (empty($values['password']) ? '' : ' ' . $this->t('Leave empty unless you want to change the stored password.')),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
      '#states' => [
        'visible' => [$state_for_public, $state_for_private],
      ],
    ];
    // If password was never set make sure it is required.
    if (empty($values['organization'])) {
      $form['password']['#states']['required'] = [$state_for_public, $state_for_private];
    }

    $state_for_not_gcp_hosted = [];
    $gceServiceAccountAuth = new GceServiceAccountAuthentication(\Drupal::service('apigee_edge.authentication.oauth_token_storage'));
    if ($gceServiceAccountAuth->isAvailable()) {
      $form['gcp_hosted'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use the default service account if this portal is hosted on GCP'),
        '#description' => $this->t("Please ensure you have added 'Apigee Developer Administrator' role to the default compute engine service account hosting this portal."),
        '#default_value' => $values['gcp_hosted'] ?? TRUE,
        '#states' => [
          'visible' => $state_for_hybrid,
        ],
      ];
      $state_for_not_gcp_hosted = [
        ':input[name="key_input_settings[gcp_hosted]"]' => ['checked' => FALSE],
      ];
    }

    $form['account_json_key'] = [
      '#type' => 'textarea',
      '#title' => $this->t('GCP service account key'),
      '#description' => $this->t("Paste the contents of the GCP service account key JSON file."),
      '#default_value' => $values['account_json_key'] ?? '',
      '#rows' => '8',
      '#states' => [
        'visible' => $state_for_hybrid + $state_for_not_gcp_hosted,
        'required' => $state_for_hybrid + $state_for_not_gcp_hosted,
      ],
    ];
    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Apigee Edge endpoint'),
      '#description' => $this->t('Apigee Edge endpoint where the API calls are being sent. For a Private Cloud installation it is in the form: %form_a or %form_b.', [
        '%form_a' => 'http://ms_IP_or_DNS:8080/v1',
        '%form_b' => 'https://ms_IP_or_DNS:TLSport/v1',
      ]),
      '#default_value' => $values['endpoint'] ?? '',
      '#attributes' => ['autocomplete' => 'off'],
      '#states' => [
        'visible' => $state_for_private,
        'required' => $state_for_private,
      ],
    ];
    $form['authorization_server_type'] = [
      '#title' => $this->t('Authorization server'),
      '#type' => 'radios',
      '#required' => TRUE,
      '#default_value' => $values['authorization_server_type'] ?? 'default',
      '#options' => [
        'default' => $this->t('Default'),
        'custom' => $this->t('Custom'),
      ],
      '#description' => $this->t('The server issuing access tokens to the client. Use the default (%authorization_server), unless using a SAML enabled organization.', [
        '%authorization_server' => Oauth::DEFAULT_AUTHORIZATION_SERVER,
      ]),
      '#states' => [
        'visible' => [
          [$state_for_public, $state_for_private],
          ':input[name="key_input_settings[auth_type]"]' => ['value' => EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH],
        ],
      ],
    ];
    $form['authorization_server'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom authorization server'),
      '#description' => $this->t('The authorization server endpoint for a SAML enabled edge org is in the form: %form.', [
        '%form' => 'https://{zonename}.login.apigee.com/oauth/token',
      ]),
      '#default_value' => $values['authorization_server'] ?? '',
      '#attributes' => ['autocomplete' => 'off'],
      '#states' => [
        'visible' => [
          [$state_for_public, $state_for_private],
          ':input[name="key_input_settings[auth_type]"]' => ['value' => EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH],
          ':input[name="key_input_settings[authorization_server_type]"]' => ['value' => 'custom'],
        ],
        'required' => [
          [$state_for_public, $state_for_private],
          ':input[name="key_input_settings[auth_type]"]' => ['value' => EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH],
          ':input[name="key_input_settings[authorization_server_type]"]' => ['value' => 'custom'],
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
          [$state_for_public, $state_for_private],
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
          [$state_for_public, $state_for_private],
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
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $input_values = $form_state->getUserInput()['key_input_settings'];
    if ($input_values['instance_type'] == EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID &&
      empty($input_values['gcp_hosted'])) {
      $account_key = $input_values['account_json_key'] ?? '';
      $json = json_decode($account_key, TRUE);
      if (empty($json['private_key']) || empty($json['client_email'])) {
        $form_state->setErrorByName('key_input_settings][account_json_key', $this->t('GCP service account key JSON file is invalid.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function processSubmittedKeyValue(FormStateInterface $form_state) {
    // Get input values.
    $input_values = $form_state->getValues();

    if (!empty($input_values)) {
      $instance_type = $input_values['instance_type'] ?? NULL;

      // Make sure the endpoint defaults are not overridden by other values.
      if ($instance_type == EdgeKeyTypeInterface::INSTANCE_TYPE_PUBLIC) {
        $input_values['endpoint'] = '';
      }
      if (empty($input_values['authorization_server_type']) || $input_values['authorization_server_type'] == 'default') {
        $input_values['authorization_server'] = '';
      }

      // Remove unneeded values if on a Hybrid instance.
      if ($instance_type == EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID) {
        $input_values['auth_type'] = '';
        $input_values['username'] = '';
        $input_values['password'] = '';
        $input_values['endpoint'] = '';
        $input_values['authorization_server_type'] = '';
        $input_values['authorization_server'] = '';
        $input_values['client_id'] = '';
        $input_values['client_secret'] = '';
        if (!empty($input_values['gcp_hosted'])) {
          $input_values['account_json_key'] = '';
        }
      }
      else {
        // Remove unneeded values if on a Public or Private instance.
        $input_values['account_json_key'] = '';
        if (!empty($input_values['gcp_hosted'])) {
          unset($input_values['gcp_hosted']);
        }
        // If password field is empty we just skip it and preserve the initial
        // password if there is one already.
        if (empty($input_values['password']) && !empty($form_state->get('key_value')['current'])) {
          $values = $this->getFormDefaultValues($form_state);
          if (!empty($values['password'])) {
            $input_values['password'] = $values['password'];
          }
        }
      }

      // Remove `key_value` so it doesn't get double encoded.
      unset($input_values['key_value']);
    }

    // Reset values to just `key_value`.
    $form_state->setValues(['key_value' => Json::encode(array_filter($input_values))]);

    return parent::processSubmittedKeyValue($form_state);
  }

  /**
   * Get authentication from values.
   */
  protected function getFormDefaultValues(FormStateInterface $form_state) {
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
    $values['authorization_server_type'] = empty($values['authorization_server']) ? 'default' : 'custom';
    return $values;
  }

}
