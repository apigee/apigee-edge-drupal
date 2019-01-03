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

use Drupal\Core\Url;
use Apigee\Edge\Exception\ApiException;
use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;

/**
 * Helper trait that contains app create form specific tweaks.
 *
 * @see \Drupal\apigee_edge\Entity\Form\AppForm
 * @see \Drupal\apigee_edge\Entity\Form\DeveloperAppCreateForm
 * @see \Drupal\apigee_edge\Entity\Form\DeveloperAppCreateFormForDeveloper
 */
trait AppCreateFormTrait {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected function alterForm(array &$form, FormStateInterface $form_state): void {
    $app_settings = \Drupal::config('apigee_edge.common_app_settings');
    $user_select = (bool) $app_settings->get('user_select');

    $api_products_options = array_map(function (ApiProductInterface $product) {
      return $product->label();
    }, $this->apiProductList());

    $multiple = $app_settings->get('multiple_products');
    $default_products = $app_settings->get('default_products') ?: [];

    $form['api_products'] = [
      '#title' => $this->entityTypeManager->getDefinition('api_product')->getPluralLabel(),
      '#required' => TRUE,
      '#options' => $api_products_options,
      '#access' => $user_select,
      '#weight' => 100,
      '#default_value' => $multiple ? $default_products : (string) reset($default_products),
      '#element_validate' => ['::validateApiProductSelection'],
    ];

    if ($app_settings->get('display_as_select')) {
      $form['api_products']['#type'] = 'select';
      $form['api_products']['#multiple'] = $multiple;
      $form['api_products']['#empty_value'] = '';
    }
    else {
      $form['api_products']['#type'] = $multiple ? 'checkboxes' : 'radios';
      if (!$multiple) {
        $form['api_products']['#options'] = ['' => $this->t('N/A')] + $form['api_products']['#options'];
      }
    }
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
      $existing_products = \Drupal::service('apigee_edge.controller.api_product')->getEntityIds();
      $sanitized_product_list = array_intersect($selected_products, $existing_products);
      if ($sanitized_product_list != $selected_products) {
        // Something went wrong...
        $form_state->setError($complete_form, $this->t('@app creation is temporarily disabled. Please contact with support.', [
          '@app' => $this->appEntityDefinition()->getSingularLabel(),
        ]));
        \Drupal::logger('apigee_edge')
          ->critical('Invalid configuration detected! "Let user select the product(s)" is disabled but the submitted app creation form did contain at least one invalid API product. App creation process has been aborted. Please verify the configuration.<br>API product ids in input: <pre>@input</pre> API Product ids on Apigee Edge: <pre>@existing</pre>', [
            'link' => Link::fromTextAndUrl($this->t('configuration'), Url::fromRoute('apigee_edge.settings.app'))->toString(),
            '@input' => print_r($selected_products, TRUE),
            '@existing' => print_r($existing_products, TRUE),
          ]);
      }

    }
  }

  /**
   * {@inheritdoc}
   */
  protected function apiProductList(): array {
    // For backward-compatibility and security reasons only return public
    // API products by default.
    return array_filter(\Drupal::entityTypeManager()->getStorage('api_product')->loadMultiple(), function (ApiProductInterface $api_product) {
      // Attribute may not exists but in that case it means public.
      return ($api_product->getAttributeValue('access') ?? 'public') === 'public';
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function saveAppCredentials(AppInterface $app, FormStateInterface $form_state): ?bool {
    // On app creation we only support creation of one app credential at this
    // moment.
    $result = FALSE;
    $app_credential_controller = $this->appCredentialController($app->getAppOwner(), $app->getName());
    $logger = \Drupal::logger('apigee_edge');

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

  /**
   * {@inheritdoc}
   */
  abstract protected function appCredentialController(string $owner, string $app_name) : AppCredentialControllerInterface;

  /**
   * {@inheritdoc}
   */
  abstract protected function appCredentialLifeTime(): int;

  /**
   * {@inheritdoc}
   */
  abstract protected function appEntityDefinition(): EntityTypeInterface;

}
