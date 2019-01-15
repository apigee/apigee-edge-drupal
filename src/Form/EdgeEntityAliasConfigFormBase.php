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
 * Base form for those Apigee Edge entities that supports aliasing.
 */
abstract class EdgeEntityAliasConfigFormBase extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this->getConfigNameWithLabels());

    $form['label'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('How to refer to a @entity_type on the UI', ['@entity_type' => $this->entityTypeName()]),
      '#collapsible' => FALSE,
    ];

    $form['label']['entity_label_singular'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Singular format'),
      '#default_value' => $config->get('entity_label_singular'),
      '#description' => $this->t('Leave empty to use the default "@singular_label" label.', ['@singular_label' => $this->originalSingularLabel()]),
    ];

    $form['label']['entity_label_plural'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Plural format'),
      '#default_value' => $config->get('entity_label_plural'),
      '#description' => $this->t('Leave empty to use the default "@plural_label" label.', ['@plural_label' => $this->originalPluralLabel()]),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config($this->getConfigNameWithLabels());

    if ($config->get('entity_label_singular') !== $form_state->getValue('entity_label_singular') || $config->get('entity_label_plural') !== $form_state->getValue('entity_label_plural')) {
      $config->set('entity_label_singular', $form_state->getValue('entity_label_singular'))
        ->set('entity_label_plural', $form_state->getValue('entity_label_plural'))
        ->save();

      // An entity label could be cached in multiple places so let's clear
      // all caches.
      drupal_flush_all_caches();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Returns the name of the config that contains the entity labels.
   *
   * @return string
   *   The name of the config.
   */
  protected function getConfigNameWithLabels(): string {
    $configs = $this->getEditableConfigNames();
    return reset($configs);
  }

  /**
   * Returns the human readable name of the entity.
   *
   * @return string
   *   The name of the entity.
   */
  abstract protected function entityTypeName() : string;

  /**
   * Returns the original singular label of the entity.
   *
   * This information can not be retrieved directly from entity annotation
   * at this moment.
   *
   * @return string
   *   The singular label.
   */
  abstract protected function originalSingularLabel(): string;

  /**
   * Returns the original plural label of the entity.
   *
   * This information can not be retrieved directly from entity annotation
   * at this moment.
   *
   * @return string
   *   The plural label.
   */
  abstract protected function originalPluralLabel(): string;

}
