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

  /**
   * Allow to confirm email address with a verification email.
   *
   * @var string
   */
  public const VERIFICATION_ACTION_VERIFY_EMAIL = 'verify_email';

  /**
   * Abort registration, display an error.
   *
   * @var string
   */
  public const VERIFICATION_ACTION_DISPLAY_ERROR_ONLY = 'display_error_only';

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

    $form['email_verification_on_registration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('When a new user registers and the developer email address is already taken on Apigee Edge but not in Drupal'),
      '#collapsible' => FALSE,
    ];

    $form['email_verification_on_registration']['verification_action'] = [
      '#type' => 'radios',
      '#options' => [
        self::VERIFICATION_ACTION_VERIFY_EMAIL => $this->t('Display an error message and send a verification email with a link that allows user to register'),
        self::VERIFICATION_ACTION_DISPLAY_ERROR_ONLY => $this->t('Display only an error message to the user'),
      ],
      '#default_value' => $config->get('verification_action') ?: self::VERIFICATION_ACTION_DISPLAY_ERROR_ONLY,
    ];

    $form['email_verification_on_registration']['verification_email_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification email subject'),
      '#default_value' => $config->get('verification_email.subject'),
      '#states' => [
        'visible' => [
          [':input[name="verification_action"]' => ['value' => self::VERIFICATION_ACTION_VERIFY_EMAIL]],
        ],
        'required' => [
          [':input[name="verification_action"]' => ['value' => self::VERIFICATION_ACTION_VERIFY_EMAIL]],
        ],
      ],
    ];

    $form['email_verification_on_registration']['verification_email_body'] = [
      // By default Drupal does not support HTML mails therefore this is
      // just a simple textarea.
      '#type' => 'textarea',
      '#title' => $this->t('Verification email content'),
      '#description' => $this->t('Available tokens: [site:name], [site:url], [user:display-name], [user:account-name], [user:mail], [site:login-url], [site:url-brief], [user:developer-email-verification-url]'),
      '#default_value' => $config->get('verification_email.body'),
      '#rows' => 10,
      '#states' => [
        'visible' => [
          [':input[name="verification_action"]' => ['value' => self::VERIFICATION_ACTION_VERIFY_EMAIL]],
        ],
        'required' => [
          [':input[name="verification_action"]' => ['value' => self::VERIFICATION_ACTION_VERIFY_EMAIL]],
        ],
      ],
      '#after_build' => ['apigee_edge_developer_settings_form_verification_email_body_after_build'],
    ];

    $form['email_verification_on_registration']['verification_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification token'),
      '#description' => $this->t('Query parameter in registration url that contains the verification token. Ex.: user/register?%token=1234567', ['%token' => $config->get('verification_token')]),
      '#default_value' => $config->get('verification_token'),
      '#states' => [
        'visible' => [
          [':input[name="verification_action"]' => ['value' => self::VERIFICATION_ACTION_VERIFY_EMAIL]],
        ],
        'required' => [
          [':input[name="verification_action"]' => ['value' => self::VERIFICATION_ACTION_VERIFY_EMAIL]],
        ],
      ],
    ];

    $form['email_verification_on_registration']['verification_token_expires'] = [
      '#type' => 'number',
      '#title' => $this->t('Verification token expires'),
      '#description' => $this->t('Number of seconds after the verification token expires. Initially provided registration data by a user is cached until the same time.'),
      '#default_value' => $config->get('verification_token_expires'),
      '#min' => 60,
      '#states' => [
        'visible' => [
          [':input[name="verification_action"]' => ['value' => self::VERIFICATION_ACTION_VERIFY_EMAIL]],
        ],
        'required' => [
          [':input[name="verification_action"]' => ['value' => self::VERIFICATION_ACTION_VERIFY_EMAIL]],
        ],
      ],
    ];

    $form['email_verification_on_registration']['verify_email_error_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Error message'),
      '#description' => $this->t('The error message on the form. Use <em>%email</em> token in message to display the email address.'),
      '#format' => $config->get('verify_email_error_message.format'),
      '#default_value' => $config->get('verify_email_error_message.value'),
      '#states' => [
        'visible' => [
          [':input[name="verification_action"]' => ['value' => self::VERIFICATION_ACTION_VERIFY_EMAIL]],
        ],
        'required' => [
          [':input[name="verification_action"]' => ['value' => self::VERIFICATION_ACTION_VERIFY_EMAIL]],
        ],
      ],
    ];
    $form['email_verification_on_registration']['display_only_error_message_content'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Error message'),
      '#description' => $this->t('The error message on the form. Use <em>%email</em> token in message to display the email address.'),
      '#format' => $config->get('display_only_error_message_content.format'),
      '#default_value' => $config->get('display_only_error_message_content.value'),
      '#states' => [
        'visible' => [
          [':input[name="verification_action"]' => ['value' => self::VERIFICATION_ACTION_DISPLAY_ERROR_ONLY]],
        ],
        'required' => [
          [':input[name="verification_action"]' => ['value' => self::VERIFICATION_ACTION_DISPLAY_ERROR_ONLY]],
        ],
      ],
    ];

    $form['email_verification_on_edit'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('When a Drupal user changes its email address and the email address is already taken on Apigee Edge but not in Drupal'),
      '#collapsible' => FALSE,
    ];
    $form['email_verification_on_edit']['user_edit_error_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Error message'),
      '#description' => $this->t('The error message on the form.'),
      '#format' => $config->get('user_edit_error_message.format'),
      '#default_value' => $config->get('user_edit_error_message.value'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('apigee_edge.developer_settings')
      ->set('verification_action', $form_state->getValue('verification_action'))
      ->set('display_only_error_message_content.value', $form_state->getValue(['display_only_error_message_content', 'value']))
      ->set('display_only_error_message_content.format', $form_state->getValue(['display_only_error_message_content', 'format']))
      ->set('verify_email_error_message.value', $form_state->getValue(['verify_email_error_message', 'value']))
      ->set('verify_email_error_message.format', $form_state->getValue(['verify_email_error_message', 'format']))
      ->set('verification_email.subject', $form_state->getValue(['verification_email_subject']))
      ->set('verification_email.body', $form_state->getValue(['verification_email_body']))
      ->set('verification_token', $form_state->getValue(['verification_token']))
      ->set('verification_token_expires', $form_state->getValue(['verification_token_expires']))
      ->set('user_edit_error_message.value', $form_state->getValue(['user_edit_error_message', 'value']))
      ->set('user_edit_error_message.format', $form_state->getValue(['user_edit_error_message', 'format']))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
