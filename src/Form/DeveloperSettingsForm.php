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

namespace Drupal\apigee_edge\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for changing Developer related settings.
 */
class DeveloperSettingsForm extends ConfigFormBase {

  public const REGISTRATION_MODE_VERIFY_EMAIL = 'verify_email';

  public const REGISTRATION_MODE_DISPLAY_ERROR_ONLY = 'display_error_only';

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['apigee_edge.developer_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_developer_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('apigee_edge.developer_settings');

    $form['registration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('If a developer email address is already taken on Edge but not in Drupal'),
      '#collapsible' => FALSE,
    ];

    $form['registration']['registration_mode'] = [
      '#type' => 'radios',
      '#options' => [
        self::REGISTRATION_MODE_VERIFY_EMAIL => $this->t('Display an error message and send a verification email with a link that allows user to register'),
        self::REGISTRATION_MODE_DISPLAY_ERROR_ONLY => $this->t('Display only an error message to the user'),
      ],
      '#default_value' => $config->get('registration_mode') ?: self::REGISTRATION_MODE_DISPLAY_ERROR_ONLY,
    ];

    $form['registration']['verification_email_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification email subject'),
      '#default_value' => $config->get('verification_email_subject'),
      '#states' => [
        'visible' => [
          [':input[name="registration_mode"]' => ['value' => self::REGISTRATION_MODE_VERIFY_EMAIL]],
        ],
        'required' => [
          [':input[name="registration_mode"]' => ['value' => self::REGISTRATION_MODE_VERIFY_EMAIL]],
        ],
      ],
    ];

    $form['registration']['verification_email_content'] = [
      // By default Drupal does not support HTML mails therefore this is
      // just a simple textarea.
      '#type' => 'textarea',
      '#title' => $this->t('Verification email content'),
      '#description' => $this->t('Available tokens: [site:name], [site:url], [user:display-name], [user:account-name], [user:mail], [site:login-url], [site:url-brief], [user:developer-email-verification-url]'),
      '#default_value' => $config->get('verification_email_content'),
      '#rows' => 10,
      '#states' => [
        'visible' => [
          [':input[name="registration_mode"]' => ['value' => self::REGISTRATION_MODE_VERIFY_EMAIL]],
        ],
        'required' => [
          [':input[name="registration_mode"]' => ['value' => self::REGISTRATION_MODE_VERIFY_EMAIL]],
        ],
      ],
      '#after_build' => ['apigee_edge_developer_settings_form_verification_email_content_after_build'],
    ];

    $form['registration']['verification_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification token'),
      '#description' => $this->t('Query parameter in registration url that contains the verification token. Ex.: user/register?%token=1234567', ['%token' => $config->get('verification_token')]),
      '#default_value' => $config->get('verification_token'),
      '#states' => [
        'visible' => [
          [':input[name="registration_mode"]' => ['value' => self::REGISTRATION_MODE_VERIFY_EMAIL]],
        ],
        'required' => [
          [':input[name="registration_mode"]' => ['value' => self::REGISTRATION_MODE_VERIFY_EMAIL]],
        ],
      ],
    ];

    $form['registration']['verification_token_expires'] = [
      '#type' => 'number',
      '#title' => $this->t('Verification token expires'),
      '#description' => $this->t('Number of seconds after the verification token expires. Initially provided registration data by a user is cached until the same time.'),
      '#default_value' => $config->get('verification_token_expires'),
      '#min' => 60,
      '#states' => [
        'visible' => [
          [':input[name="registration_mode"]' => ['value' => self::REGISTRATION_MODE_VERIFY_EMAIL]],
        ],
        'required' => [
          [':input[name="registration_mode"]' => ['value' => self::REGISTRATION_MODE_VERIFY_EMAIL]],
        ],
      ],
    ];

    $form['registration']['verify_email_error_message_content'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Error message'),
      '#description' => $this->t('The error message on the form. Use <em>%email</em> token in message to display the email address.'),
      '#format' => $config->get('verify_email_error_message_content.format'),
      '#default_value' => $config->get('verify_email_error_message_content.value'),
      '#states' => [
        'visible' => [
          [':input[name="registration_mode"]' => ['value' => self::REGISTRATION_MODE_VERIFY_EMAIL]],
        ],
        'required' => [
          [':input[name="registration_mode"]' => ['value' => self::REGISTRATION_MODE_VERIFY_EMAIL]],
        ],
      ],
    ];
    $form['registration']['display_only_error_message_content'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Error message'),
      '#description' => $this->t('The error message on the form. Use <em>%email</em> token in message to display the email address.'),
      '#format' => $config->get('display_only_error_message_content.format'),
      '#default_value' => $config->get('display_only_error_message_content.value'),
      '#states' => [
        'visible' => [
          [':input[name="registration_mode"]' => ['value' => self::REGISTRATION_MODE_DISPLAY_ERROR_ONLY]],
        ],
        'required' => [
          [':input[name="registration_mode"]' => ['value' => self::REGISTRATION_MODE_DISPLAY_ERROR_ONLY]],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('apigee_edge.developer_settings')
      ->set('registration_mode', $form_state->getValue('registration_mode'))
      ->set('display_only_error_message_content.value', $form_state->getValue(['display_only_error_message_content', 'value']))
      ->set('display_only_error_message_content.format', $form_state->getValue(['display_only_error_message_content', 'format']))
      ->set('verify_email_error_message_content.value', $form_state->getValue(['verify_email_error_message_content', 'value']))
      ->set('verify_email_error_message_content.format', $form_state->getValue(['verify_email_error_message_content', 'format']))
      ->set('verification_email_subject', $form_state->getValue(['verification_email_subject']))
      ->set('verification_email_content', $form_state->getValue(['verification_email_content']))
      ->set('verification_token', $form_state->getValue(['verification_token']))
      ->set('verification_token_expires', $form_state->getValue(['verification_token_expires']))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
