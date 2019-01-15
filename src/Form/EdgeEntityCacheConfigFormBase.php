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

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base cache expiration config form for Apigee Edge entities.
 */
abstract class EdgeEntityCacheConfigFormBase extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this->getConfigNameWithCacheSettings());
    $form['cache'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Caching'),
      '#collapsible' => FALSE,
    ];
    $form['cache']['cache_expiration'] = [
      '#type' => 'number',
      '#title' => $this->t('Expires'),
      '#description' => $this->t('Number of <strong>seconds</strong> until a cached item expires. Use <em>-1</em> to cache items until they have been updated on the Developer Portal (ignore changes made on the Apigee Edge Management UI or in an external application). Use <em>0</em> to completely disable caching.'),
      '#default_value' => $config->get('cache_expiration'),
      '#min' => -1,
      '#required' => TRUE,
    ];
    $form['cache']['actions'] = [
      '#type' => 'actions',
    ];
    $form['cache']['actions']['invalidate_cache'] = [
      '#type' => 'submit',
      '#value' => $this->t('Invalidate cache'),
      '#limit_validation_errors' => [],
      '#submit' => ['::invalidateCache'],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config($this->getConfigNameWithCacheSettings())
      ->set('cache_expiration', $form_state->getValue('cache_expiration'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Submit handler that invalidates stored cache items from of an entity type.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  public function invalidateCache(array $form, FormStateInterface $form_state) {
    Cache::invalidateTags([$this->getEntityType()]);
  }

  /**
   * Returns the name of the config object that contains "cache_expiration" key.
   *
   * @return string
   *   The if of a configuration object.
   */
  protected function getConfigNameWithCacheSettings(): string {
    $configs = $this->getEditableConfigNames();
    return reset($configs);
  }

  /**
   * Name of the entity type that cache expiration is controlled here.
   *
   * @return string
   *   The id of an entity type.
   */
  abstract protected function getEntityType(): string;

}
