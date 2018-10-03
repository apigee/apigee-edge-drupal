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
 * Provides a form for changing connection related settings.
 */
class ConnectionConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.client',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_connection_config_form.';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['connect_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Connection timeout'),
      '#description' => $this->t('Number of seconds before an HTTP connection to Apigee Edge is assumed to have timed out.'),
      '#default_value' => $this->config('apigee_edge.client')->get('http_client_connect_timeout'),
      '#min' => 0,
      '#step' => 0.1,
      '#required' => TRUE,
    ];

    $form['request_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Request timeout'),
      '#description' => $this->t('Number of seconds before an HTTP response from Apigee Edge is assumed to have timed out.'),
      '#default_value' => $this->config('apigee_edge.client')->get('http_client_timeout'),
      '#min' => 0,
      '#step' => 0.1,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('apigee_edge.client')
      ->set('http_client_connect_timeout', $form_state->getValue('connect_timeout'))
      ->set('http_client_timeout', $form_state->getValue('request_timeout'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
