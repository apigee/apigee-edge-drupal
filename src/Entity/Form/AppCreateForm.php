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

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Apigee\Edge\Exception\ApiException;
use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;

/**
 * Base entity form for developer- and team (company) app create forms.
 */
abstract class AppCreateForm extends AppForm {

  /**
   * The API product controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface
   */
  protected $apiProductController;

  /**
   * AppCreateForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface $api_product_controller
   *   The API product controller service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ApiProductControllerInterface $api_product_controller) {
    parent::__construct($entity_type_manager);
    $this->apiProductController = $api_product_controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('apigee_edge.controller.api_product')
    );
  }

  /**
   * {@inheritdoc}
   */
  final public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $this->alterFormBeforeApiProductElement($form, $form_state);
    $form['api_products'] = $this->apiProductsFormElement($form, $form_state);
    $this->alterFormWithApiProductElement($form, $form_state);
    return $form;
  }

  /**
   * Allows to alter the form before API products gets added.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function alterFormBeforeApiProductElement(array &$form, FormStateInterface $form_state): void {}

  /**
   * Allows to alter the form after API products form element have been added.
   *
   * @param array $form
   *   Form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function alterFormWithApiProductElement(array &$form, FormStateInterface $form_state): void {}

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
    $app_settings = $this->config('apigee_edge.common_app_settings');
    $user_select = (bool) $app_settings->get('user_select');

    $api_products_options = array_map(function (ApiProductInterface $product) {
      return $product->label();
    }, $this->apiProductList($form, $form_state));

    $multiple = $app_settings->get('multiple_products');
    $default_products = $app_settings->get('default_products') ?: [];

    $element = [
      '#title' => $this->entityTypeManager->getDefinition('api_product')->getPluralLabel(),
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
      $existing_products = $this->apiProductController->getEntityIds();
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

  /**
   * {@inheritdoc}
   */
  protected function saveAppCredentials(AppInterface $app, FormStateInterface $form_state): ?bool {
    // On app creation we only support creation of one app credential at this
    // moment.
    $result = FALSE;
    $app_credential_controller = $this->appCredentialController($app->getAppOwner(), $app->getName());
    $logger = $this->logger('apigee_edge');

    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential[] $credentials */
    $credentials = $app->getCredentials();
    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential $credential */
    $credential = reset($credentials);
    $selected_products = array_values(array_filter((array) $form_state->getValue('api_products')));

    try {
      if ($this->appCredentialLifeTime() === 0) {
        $app_credential_controller->addProducts($credential->id(), $selected_products);
      }
      else {
        $app_credential_controller->delete($credential->id());
        // The value of -1 indicates no set expiry. But the value of 0 is not
        // acceptable by the server (InvalidValueForExpiresIn).
        $app_credential_controller->generate($selected_products, $app->getAttributes(), $app->getCallbackUrl(), [], $this->appCredentialLifeTime() * 86400000);
      }
      $result = TRUE;
    }
    catch (ApiException $exception) {
      $context = [
        '%app_name' => $app->label(),
        '%owner' => $app->getAppOwner(),
        'link' => $app->toLink()->toString(),
      ];
      $context += Error::decodeException($exception);
      $logger->error('Unable to set up app credentials on a created app. App name: %app_name. Owner: %owner. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
      try {
        // Apps without an associated API product should not exist in
        // Apigee Edge because they cause problems.
        $app->delete();
      }
      catch (EntityStorageException $exception) {
        $context = Error::decodeException($exception) + $context;
        $logger->critical('Unable automatically remove %app_name app owned by %owner after app credential set up has failed meanwhile app creation. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
        // save() is not going to redirect the user in this case, but.
        $form_state->setRedirectUrl($app->toUrl('collection'));
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function saveButtonLabel() : TranslatableMarkup {
    return $this->t('Add @app', ['@app' => $this->appEntityDefinition()->getLowercaseLabel()]);
  }

}
