<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a trait for api product selection form elements.
 */
trait ApiProductSelectionFormTrait {

  /**
   * Returns the API Products form element element.
   *
   * Form and form state is only passed to be able filter API products that
   * should be displayed.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The API product render element
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see apiProductList()
   */
  final protected function apiProductsFormElement(array $form, FormStateInterface $form_state): array {
    $entity_type_manager = \Drupal::entityTypeManager();
    $app_settings = \Drupal::config('apigee_edge.common_app_settings');
    $user_select = (bool) $app_settings->get('user_select');

    $api_products_options = array_map(function (ApiProductInterface $product) {
      return $product->label();
    }, $this->apiProductList($form, $form_state));

    $multiple = $app_settings->get('multiple_products');
    $default_products = $app_settings->get('default_products') ?: [];

    $element = [
      '#title' => $entity_type_manager->getDefinition('api_product')->getPluralLabel(),
      '#required' => TRUE,
      '#options' => $api_products_options,
      '#access' => $user_select,
      '#weight' => 100,
      '#default_value' => $multiple ? $default_products : (string) reset($default_products),
      '#element_validate' => ['::validateApiProductSelection'],
    ];

    if ($app_settings->get('display_as_select')) {
      $element['#type'] = 'select';
      $element['#multiple'] = $multiple;
      $element['#empty_value'] = '';
    }
    else {
      $element['#type'] = $multiple ? 'checkboxes' : 'radios';
      if (!$multiple) {
        $element['#options'] = ['' => $this->t('N/A')] + $element['#options'];
      }
    }

    return $element;
  }

  /**
   * Element validate callback for the API product list.
   *
   * Ensures that even if "Let user select the product(s)" is disabled the
   * submitted form contains at least one valid API product.
   * (It could happen that someone changed this configuration from CMI but
   * forgot to select at least one "Default API product" or the selected
   * default API product does not exist anymore.)
   *
   * @param array $element
   *   Element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   The complete form.
   */
  final public function validateApiProductSelection(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // Field is required so we only need to validate this if the user does not
    // have access to the form element.
    if (!$element['#access']) {
      $selected_products = array_values(array_filter((array) $form_state->getValue($element['#parents'])));
      // It is faster to collect existing API product names from Apigee Edge
      // like this.
      $existing_products = \Drupal::service('apigee_edge.controller.api_product')->getEntityIds();
      $sanitized_product_list = array_intersect($selected_products, $existing_products);
      if ($sanitized_product_list != $selected_products) {
        // Something went wrong...
        $form_state->setError($complete_form, $this->t('@app creation is temporarily disabled. Please contact with support.', [
          '@app' => $this->appEntityDefinition()->getSingularLabel(),
        ]));
        $this->logger('apigee_edge')
          ->critical('Invalid configuration detected! "Let user select the product(s)" is disabled but the submitted app creation form did contain at least one invalid API product. App creation process has been aborted. Please verify the configuration.<br>API product ids in input: <pre>@input</pre> API Product ids on Apigee Edge: <pre>@existing</pre>', [
            'link' => Link::fromTextAndUrl($this->t('configuration'), Url::fromRoute('apigee_edge.settings.general_app'))->toString(),
            '@input' => print_r($selected_products, TRUE),
            '@existing' => print_r($existing_products, TRUE),
          ]);
      }
    }
  }

}
