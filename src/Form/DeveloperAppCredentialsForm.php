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
 * Provides a form for changing Developer app credentials related settings.
 */
class DeveloperAppCredentialsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.developer_app_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_app_credentials_form.';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Credential lifetime'),
      '#collapsible' => FALSE,
    ];

    // The value of -1 indicates no set expiry. But the value of 0 is not
    // acceptable by the server (InvalidValueForExpiresIn),
    // so 0 is transformed to -1 while saving the developer app.
    $form['wrapper']['credential_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Default API key lifetime in days'),
      '#default_value' => $this->config('apigee_edge.developer_app_settings')->get('credential_lifetime'),
      '#description' => $this->t('When an app is newly-created, this is the default number of days until its API Key expires. A value of 0 indicates no set expiry.'),
      '#min' => 0,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('apigee_edge.developer_app_settings')
      ->set('credential_lifetime', $form_state->getValue('credential_lifetime'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
