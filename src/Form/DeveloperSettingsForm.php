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

class DeveloperSettingsForm extends ConfigFormBase {

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
        'auto_create' => $this->t('Automatically create a new user in Drupal and send a confirmation email to that user to verify email'),
        'display_error' => $this->t('Display an error message to the user'),
      ],
      '#default_value' => $config->get('registration_mode') ?: 'display_error',
    ];

    $form['registration']['registration_mode_error_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Error message'),
      '#description' => $this->t('Use <em>%email</em> in message to display provided email address.'),
      '#format' => $config->get('registration_mode_error_message.format'),
      '#default_value' => $config->get('registration_mode_error_message.value'),
      '#states' => [
        'visible' => [
          [':input[name="registration_mode"]' => ['value' => 'display_error']],
        ],
        'required' => [
          [':input[name="registration_mode"]' => ['value' => 'display_error']],
        ],
      ],
      '#after_build' => ['apigee_edge_developer_settings_form_registration_mode_error_message_after_build'],
    ];

    return parent::buildForm($form, $form_state);
  }

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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('apigee_edge.developer_settings')
      ->set('registration_mode', $form_state->getValue('registration_mode'))
      ->set('registration_mode_error_message.value', $form_state->getValue(['registration_mode_error_message', 'value']))
      ->set('registration_mode_error_message.format', $form_state->getValue(['registration_mode_error_message', 'format']))
      ->save();

    // TODO Do we need to clear cache to update message?
    parent::submitForm($form, $form_state);
  }

}
