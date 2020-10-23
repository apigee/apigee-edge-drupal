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

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form builder for general app settings.
 */
class AppSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * AppSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

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
    return 'apigee_edge_app_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $common_app_settings = $this->config('apigee_edge.common_app_settings');

    // Someone has overridden the default setting.
    if (!$common_app_settings->get('multiple_products')) {
      $this->messenger()->addWarning($this->t('Access to multiple API products will be retained until an app is edited and the developer is prompted to confirm a single API product selection.'));
    }

    /** @var string[] $default_products */
    $default_products = $common_app_settings->get('default_products') ?: [];
    $product_list = [];

    try {
      /** @var \Drupal\apigee_edge\Entity\ApiProduct $product */
      foreach ($this->entityTypeManager->getStorage('api_product')->loadMultiple() as $product) {
        $product_list[$product->id()] = $product->label();
      }
    }
    catch (EntityStorageException $e) {
      // Apigee Edge credentials are missing/incorrect or something else went
      // wrong. Do not redirect the user to the error page.
      $form['actions']['submit']['#disabled'] = TRUE;
      $this->messenger()->addError($this->t('Unable to retrieve API product list from Apigee Edge. Please ensure that <a href=":link">Apigee Edge connection settings</a> are correct.', [
        ':link' => Url::fromRoute('apigee_edge.settings')->toString(),
      ]));
      return $form;
    }

    $form['api_product'] = [
      '#id' => 'api_product',
      '#type' => 'details',
      '#title' => $this->t('API product association settings'),
      '#open' => TRUE,
    ];

    $form['api_product']['multiple_products'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multiple products'),
      '#description' => $this->t('Allow users to select multiple products.'),
      '#default_value' => $common_app_settings->get('multiple_products'),
    ];

    $form['api_product']['display_as_select'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the API Product widget as a select box (instead of checkboxes/radios)'),
      '#default_value' => $common_app_settings->get('display_as_select'),
    ];

    $form['api_product']['user_select'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Let user select the product(s)'),
      '#default_value' => $common_app_settings->get('user_select'),
      '#ajax' => [
        'callback' => '::apiProductListCallback',
        'wrapper' => 'default-api-product-multiple',
        'progress' => [
          'type' => 'throbber',
          'message' => '',
        ],
      ],
    ];

    // It's necessary to add a wrapper because if the ID is added to the
    // checkboxes form element, then that will not be properly rendered
    // (the label gets duplicated).
    $form['api_product']['default_api_product_multiple_container'] = [
      '#type' => 'container',
      '#id' => 'default-api-product-multiple',
    ];

    $form['api_product']['default_api_product_multiple_container']['default_api_product_multiple'] = [
      '#type' => 'checkboxes',
      '#title' => $common_app_settings->get('multiple_products') ? $this->t('Default API products') : $this->t('Default API product'),
      '#options' => $product_list,
      '#default_value' => $default_products,
      '#required' => $form_state->getValue('user_select') === NULL ? !(bool) $common_app_settings->get('user_select') : !(bool) $form_state->getValue('user_select'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback for the "user_select" checkbox.
   *
   * Set 'default_api_product_multiple' checkboxes form element as required
   * if the 'user_select' is unchecked, else not required.
   * Use AJAX instead of #states because #required in #states is not
   * working properly.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   *
   * @see https://www.drupal.org/project/drupal/issues/2855139
   */
  public function apiProductListCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#default-api-product-multiple', $this->renderer->render($form['api_product']['default_api_product_multiple_container'])));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('apigee_edge.common_app_settings')
      ->set('multiple_products', $form_state->getValue('multiple_products'))
      ->set('display_as_select', $form_state->getValue('display_as_select'))
      ->set('user_select', $form_state->getValue('user_select'))
      ->set('default_products', array_values(array_filter($form_state->getValue('default_api_product_multiple'))))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
