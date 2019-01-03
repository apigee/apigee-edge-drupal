<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides configuration form for app callback settings.
 */
class AppCallbackUrlSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.common_app_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_app_callback_url_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $app_settings = $this->config('apigee_edge.common_app_settings');

    $form['callback_url'] = [
      '#type' => 'details',
      '#title' => $this->t('Callback URL validation settings'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['callback_url']['pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern'),
      '#default_value' => $app_settings->get('callback_url_pattern'),
      '#description' => $this->t('Regular expression that a Callback URL should match. Default is "^https?:\/\/.*$" that ensures callback url starts with either <em>http://</em> or <em>https://</em>.'),
      '#required' => TRUE,
    ];
    $form['callback_url']['pattern_error_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Validation error message'),
      '#default_value' => $app_settings->get('callback_url_pattern_error_message'),
      '#description' => $this->t('Client-side validation error message if a callback URL does not match.'),
      '#required' => TRUE,
    ];
    $form['callback_url']['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#default_value' => $app_settings->get('callback_url_description'),
      '#description' => $this->t('Description of a Callback URL field.'),
    ];
    $form['callback_url']['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $app_settings->get('callback_url_placeholder'),
      '#description' => $this->t('Placeholder for a Callback URL field.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if (strpos($form_state->getValue(['callback_url', 'pattern'], ''), '^http') === FALSE) {
      $form_state->setError($form['callback_url']['pattern'], $this->t('The pattern should start with <em>^http</em> to limit the acceptable protocols.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('apigee_edge.common_app_settings')
      ->set('callback_url_pattern', $form_state->getValue(['callback_url', 'pattern']))
      ->set('callback_url_pattern_error_message', $form_state->getValue(['callback_url', 'pattern_error_message']))
      ->set('callback_url_description', $form_state->getValue(['callback_url', 'description']))
      ->set('callback_url_placeholder', $form_state->getValue(['callback_url', 'placeholder']))
      ->save();

    // Clear all caches - especially app callback url field's configuration.
    drupal_flush_all_caches();

    parent::submitForm($form, $form_state);
  }

}
