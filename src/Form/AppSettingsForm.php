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

use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides configuration form builder for changing app settings.
 *
 * If we would like to call company apps and developer apps in the same name
 * then this form should take care of the update of both configurations.
 * In general, it is better that we are allowing to call them differently
 * thanks to their dedicated entity label configurations.
 */
class AppSettingsForm extends ConfigFormBase {

  use CachedEntityConfigurationFormAwareTrait;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.common_app_settings',
      'apigee_edge.developer_app_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_app_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $generalConfig = $this->config('apigee_edge.common_app_settings');
    $devAppConfig = $this->config('apigee_edge.developer_app_settings');

    $form['api_product'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API Product'),
      '#collapsible' => FALSE,
    ];

    $form['api_product']['display_as_select'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the API Product widget as a select box (instead of checkboxes/radios)'),
      '#default_value' => $generalConfig->get('display_as_select'),
    ];

    $form['api_product']['associate_apps'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Associate apps with API Products'),
      '#default_value' => $generalConfig->get('associate_apps'),
    ];

    $form['api_product']['user_select'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Let user select the product(s)'),
      '#default_value' => $generalConfig->get('user_select'),
      '#states' => [
        'visible' => [
          ':input[name="associate_apps"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['api_product']['multiple_products'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow selecting multiple products'),
      '#default_value' => $generalConfig->get('multiple_products'),
      '#states' => [
        'visible' => [
          ':input[name="user_select"]' => [
            'visible' => TRUE,
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['api_product']['require'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require at least one product'),
      '#default_value' => $generalConfig->get('require'),
      '#states' => [
        'visible' => [
          ':input[name="user_select"]' => [
            'visible' => TRUE,
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    /** @var string[] $default_products */
    $default_products = $generalConfig->get('default_products') ?: [];
    $product_list = [];
    /** @var \Drupal\apigee_edge\Entity\ApiProduct[] $products */
    try {
      $products = ApiProduct::loadMultiple();
      foreach ($products as $product) {
        $product_list[$product->id()] = $product->getDisplayName();
      }
    }
    catch (EntityStorageException $e) {
      // Apigee Edge credentials are missing/incorrect or something else went
      // wrong. Do not redirect the user to the error page.
      $product_list = [];
      $this->messenger()->addError($this->t('Unable to retrieve API product list from Apigee Edge. Please ensure that <a href=":link">Apigee Edge connection settings</a> are correct.'), [
        ':link' => Url::fromRoute('apigee_edge.settings')->toString(),
      ]);
    }

    $form['api_product']['default_api_product_single'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default API Product'),
      '#options' => ['' => $this->t('N/A')] + $product_list,
      '#default_value' => empty($default_products) ? '' : reset($default_products),
      '#states' => [
        'visible' => [
          ':input[name="multiple_products"]' => [
            'checked' => FALSE,
            'visible' => TRUE,
          ],
        ],
      ],
    ];

    $form['api_product']['default_api_product_multiple'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Default API Product'),
      '#options' => $product_list,
      '#default_value' => $default_products,
      '#states' => [
        'visible' => [
          [
            ':input[name="multiple_products"]' => [
              'checked' => TRUE,
              'visible' => TRUE,
            ],
          ],
          'or',
          [
            ':input[name="user_select"]' => [
              'checked' => FALSE,
              'visible' => TRUE,
            ],
          ],
        ],
      ],
    ];

    $form['label'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('How to refer to an Application on the UI'),
      '#collapsible' => FALSE,
    ];

    $form['label']['entity_label_singular'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Singular format'),
      '#default_value' => $devAppConfig->get('entity_label_singular'),
      '#description' => $this->t('Leave empty to use the default "Developer App" label.'),
    ];

    $form['label']['entity_label_plural'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Plural format'),
      '#default_value' => $devAppConfig->get('entity_label_plural'),
      '#description' => $this->t('Leave empty to use the default "Developer Apps" label.'),
    ];

    $form += $this->addCacheConfigElements($form, $form_state);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO Empty Default API products list should not be saved when form
    // builder was not able to retrieve API Product list from Apigee Edge.
    // We do not want to override (clear) previously configured list of
    // default API product.
    $generalConfig = \Drupal::configFactory()->getEditable('apigee_edge.common_app_settings');
    $devAppConfig = \Drupal::configFactory()->getEditable('apigee_edge.developer_app_settings');

    $config_names = [
      'display_as_select',
      'associate_apps',
      'user_select',
      'multiple_products',
      'require',
    ];

    foreach ($config_names as $name) {
      $generalConfig->set($name, $form_state->getValue($name));
    }

    $default_products = [];
    if ($form_state->getValue('associate_apps')) {
      if ($form_state->getValue('user_select')) {
        if ($form_state->getValue('multiple_products')) {
          $default_products = $form_state->getValue('default_api_product_multiple');
        }
        else {
          $default_products = [$form_state->getValue('default_api_product_single')];
        }
      }
      else {
        $default_products = $form_state->getValue('default_api_product_multiple');
      }
    }
    $default_products = array_values(array_filter($default_products));

    $generalConfig->set('default_products', $default_products);
    $generalConfig->save();

    if ($devAppConfig->get('entity_label_singular') !== $form_state->getValue('entity_label_singular') || $devAppConfig->get('entity_label_plural') !== $form_state->getValue('entity_label_plural')) {
      $this->configFactory->getEditable('apigee_edge.developer_app_settings')
        ->set('entity_label_singular', $form_state->getValue('entity_label_singular'))
        ->set('entity_label_plural', $form_state->getValue('entity_label_plural'))
        ->save();

      // Clearing required caches.
      drupal_flush_all_caches();
    }

    $this->saveCacheConfiguration($form, $form_state);

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigNameWithCacheSettings() {
    return 'apigee_edge.developer_app_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return 'developer_app';
  }

}
