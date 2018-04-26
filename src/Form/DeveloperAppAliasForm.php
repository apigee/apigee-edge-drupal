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
 * Provides a form for changing Developer App aliases.
 */
class DeveloperAppAliasForm extends ConfigFormBase {

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
    return 'apigee_edge_app_alias_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('apigee_edge.developer_app_settings');

    $form['label'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('How to refer to an Application on the UI'),
      '#collapsible' => FALSE,
    ];

    $form['label']['entity_label_singular'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Singular format'),
      '#default_value' => $config->get('entity_label_singular'),
      '#description' => $this->t('Leave empty to use the default "Developer App" label.'),
    ];

    $form['label']['entity_label_plural'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Plural format'),
      '#default_value' => $config->get('entity_label_plural'),
      '#description' => $this->t('Leave empty to use the default "Developer Apps" label.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::configFactory()->getEditable('apigee_edge.developer_app_settings');

    if ($config->get('entity_label_singular') !== $form_state->getValue('entity_label_singular') || $config->get('entity_label_plural') !== $form_state->getValue('entity_label_plural')) {
      $this->configFactory->getEditable('apigee_edge.developer_app_settings')
        ->set('entity_label_singular', $form_state->getValue('entity_label_singular'))
        ->set('entity_label_plural', $form_state->getValue('entity_label_plural'))
        ->save();

      // Clearing required caches.
      drupal_flush_all_caches();
    }

    parent::submitForm($form, $form_state);
  }

}
