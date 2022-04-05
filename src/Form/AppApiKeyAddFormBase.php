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

namespace Drupal\apigee_edge\Form;

use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Apigee\Edge\Structure\CredentialProductInterface;
use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides app API key add base form.
 */
abstract class AppApiKeyAddFormBase extends FormBase {

  /**
   * The app entity.
   *
   * @var \Drupal\apigee_edge\Entity\AppInterface
   */
  protected $app;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_app_api_key_add_form';
  }

  /**
   * Returns the app credential controller.
   *
   * @param string $owner
   *   The developer id (UUID), email address or team (company) name.
   * @param string $app_name
   *   The name of an app.
   *
   * @return \Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface
   *   The app api-key controller.
   */
  abstract protected function appCredentialController(string $owner, string $app_name) : AppCredentialControllerInterface;

  /**
   * Returns the redirect url for the app.
   *
   * @return \Drupal\Core\Url
   *   The redirect url.
   */
  abstract protected function getRedirectUrl(): Url;

  /**
   * Returns the list of API product that the user can see on the form.
   *
   * @return \Drupal\apigee_edge\Entity\ApiProductInterface[]
   *   Array of API product entities.
   */
  abstract protected function apiProductList(array $form, FormStateInterface $form_state): array;

  /**
   * Returns the app owner id. This needs to come from the route.
   *
   * @return string
   *   The app owner id.
   */
  abstract protected function getAppOwner(): string;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AppInterface $app = NULL) {
    $this->app = $app;

    $form['owner'] = [
      '#type' => 'value',
      '#value' => $this->getAppOwner(),
    ];

    $form['message'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t('Do you really want to create a new API key for this @entity_type?', [
        '@entity_type' => mb_strtolower($app->getEntityType()->getSingularLabel()),
      ]),
    ];

    $form['expiry'] = [
      '#type' => 'select',
      '#title' => $this->t('Set an expiry date'),
      '#required' => TRUE,
      '#options' => [
        'never' => $this->t('Never'),
        'date' => $this->t('Date'),
      ],
      '#default_value' => 'never',
    ];

    $form['expiry_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Select date'),
      '#states' => [
        'visible' => [
          ':input[name="expiry"]' => ['value' => 'date'],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#attributes' => ['class' => ['button']],
        '#url' => $this->getRedirectUrl(),
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Confirm'),
        '#button_type' => 'primary',
      ],
    ];

    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.components';
    $form['#attributes']['class'][] = 'apigee-edge--form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $expiry = $form_state->getValue('expiry');
    $expiry_date = $form_state->getValue('expiry_date');

    // Validate expiration date.
    if ($expiry === 'date') {
      if ((new \DateTimeImmutable($expiry_date))->diff(new \DateTimeImmutable())->invert !== 1) {
        $form_state->setError($form['expiry_date'], $this->t('The expiration date must be a future date.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $expiry = $form_state->getValue('expiry');
    $expiry_date = $form_state->getValue('expiry_date');
    $expires_in = $expiry === 'date' ? (strtotime($expiry_date) - time()) * 1000 : -1;
    $selected_products = [];

    $api_products = $this->getApiProductsForApp($this->app);
    if (count($api_products)) {
      $selected_products = array_map(function (CredentialProductInterface $api_product) {
        return $api_product->getApiproduct();
      }, $api_products);
    }

    $args = [
      '@app' => $this->app->label(),
    ];

    try {
      $this->appCredentialController($this->app->getAppOwner(), $this->app->getName())
        ->generate($selected_products, $this->app->getAttributes(), $this->app->getCallbackUrl() ?? "", [], $expires_in);
      Cache::invalidateTags($this->app->getCacheTags());
      $this->messenger()->addStatus($this->t('New API key added to @app.', $args));
      $form_state->setRedirectUrl($this->getRedirectUrl());
    }
    catch (\Exception $exception) {
      $this->messenger()->addError($this->t('Failed to add API key for @app.', $args));
    }
  }

  /**
   * Helper to find API products based on the recently active API key.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity.
   *
   * @return \Apigee\Edge\Structure\CredentialProductInterface[]|array
   *   An array of API products.
   */
  protected function getApiProductsForApp(AppInterface $app): array {
    $approved_credentials = array_filter($app->getCredentials(), function (AppCredentialInterface $credential) {
      return $credential->getStatus() === AppCredentialInterface::STATUS_APPROVED;
    });

    // Find the recently active one.
    usort($approved_credentials, function (AppCredentialInterface $a, AppCredentialInterface $b) {
      return $b->getIssuedAt() <=> $a->getIssuedAt();
    });

    return count($approved_credentials) ? $approved_credentials[0]->getApiProducts() : [];
  }

}
