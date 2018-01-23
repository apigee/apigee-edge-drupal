<?php

namespace Drupal\apigee_edge\Form;

use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides configuration form builder for changing app settings.
 */
class AppSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'apigee_edge.appsettings',
      'apigee_edge.entity_labels',
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
    $config = $this->config('apigee_edge.appsettings');
    $label_config = $this->config('apigee_edge.entity_labels');

    $form['api_product'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API Product'),
      '#collapsible' => FALSE,
    ];

    $form['api_product']['display_as_select'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the API Product widget as a select box (instead of checkboxes/radios)'),
      '#default_value' => $config->get('display_as_select'),
    ];

    $form['api_product']['associate_apps'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Associate apps with API Products'),
      '#default_value' => $config->get('associate_apps'),
    ];

    $form['api_product']['user_select'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Let user select the product(s)'),
      '#default_value' => $config->get('user_select'),
      '#states' => [
        'visible' => [
          ':input[name="associate_apps"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['api_product']['multiple_products'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow selecting multiple products'),
      '#default_value' => $config->get('multiple_products'),
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
      '#default_value' => $config->get('require'),
      '#states' => [
        'visible' => [
          ':input[name="user_select"]' => [
            'visible' => TRUE,
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    /** @var \Drupal\apigee_edge\Entity\ApiProduct[] $products */
    $products = ApiProduct::loadMultiple();
    /** @var string[] $default_products */
    $default_products = $config->get('default_products') ?: [];
    $product_list = [];
    foreach ($products as $product) {
      $product_list[$product->id()] = $product->getDisplayName();
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

    $form['callback_url'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Callback URL'),
      '#collapsible' => FALSE,
    ];

    $form['callback_url']['callback_url_visible'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show callback URL field'),
      '#default_value' => $config->get('callback_url_visible'),
    ];

    $form['callback_url']['callback_url_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require callback URL'),
      '#default_value' => $config->get('callback_url_required'),
      '#states' => [
        'visible' => [
          ':input[name="callback_url_visible"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['description'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('App description'),
      '#collapsible' => FALSE,
    ];

    $form['description']['description_visible'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show description field'),
      '#default_value' => $config->get('description_visible'),
    ];

    $form['description']['description_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require app description'),
      '#default_value' => $config->get('description_required'),
      '#states' => [
        'visible' => [
          ':input[name="description_visible"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['label'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('How to refer to an Application on the UI'),
      '#collapsible' => FALSE,
    ];

    $form['label']['app_label_singular'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Singular format'),
      '#default_value' => $label_config->get('app_label_singular'),
      '#description' => $this->t('Leave empty to use the default'),
    ];

    $form['label']['app_label_plural'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Plural format'),
      '#default_value' => $label_config->get('app_label_plural'),
      '#description' => $this->t('Leave empty to use the default'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $appSettingsConfig = \Drupal::configFactory()->getEditable('apigee_edge.appsettings');

    $config_names = [
      'display_as_select',
      'associate_apps',
      'user_select',
      'multiple_products',
      'require',
      'description_visible',
      'description_required',
      'callback_url_visible',
      'callback_url_required',
    ];

    foreach ($config_names as $name) {
      $appSettingsConfig->set($name, $form_state->getValue($name));
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

    $appSettingsConfig->set('default_products', $default_products);
    $appSettingsConfig->save();

    $storedLabels = $this->configFactory->get('apigee_edge.entity_labels');
    if ($storedLabels->get('app_label_singular') !== $form_state->getValue('app_label_singular') || $storedLabels->get('app_label_plural') !== $form_state->getValue('app_label_plural')) {
      $this->configFactory->getEditable('apigee_edge.entity_labels')
        ->set('app_label_singular', $form_state->getValue('app_label_singular'))
        ->set('app_label_plural', $form_state->getValue('app_label_plural'))
        ->save();

      // Clearing required caches.
      drupal_flush_all_caches();
    }

    parent::submitForm($form, $form_state);
  }

}
