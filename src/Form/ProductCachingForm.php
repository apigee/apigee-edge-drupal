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

class ProductCachingForm extends ConfigFormBase {

  use CachedEntityConfigurationFormAwareTrait;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.api_product_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_api_product_caching_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form += $this->addCacheConfigElements($form, $form_state);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->saveCacheConfiguration($form, $form_state);

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigNameWithCacheSettings() {
    return 'apigee_edge.api_product_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return 'api_product';
  }

}
