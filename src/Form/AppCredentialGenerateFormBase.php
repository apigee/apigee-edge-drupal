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

use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\apigee_edge\Entity\Form\ApiProductSelectionFormTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides app credential generate base form.
 */
abstract class AppCredentialGenerateFormBase extends FormBase {

  use ApiProductSelectionFormTrait;

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
    return 'apigee_edge_app_credential_generate_form';
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
   *   The app credential controller.
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

    $form['api_products'] = $this->apiProductsFormElement($form, $form_state);

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Generate credential'),
        '#button_type' => 'primary',
      ],
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('Cancel'),
        '#attributes' => ['class' => ['button']],
        '#url' => $this->getRedirectUrl(),
      ],
    ];

    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.components';
    $form['#attributes']['class'][] = 'apigee-edge--form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_products = array_values(array_filter((array) $form_state->getValue('api_products')));
    $args = [
      '@app' => $this->app->label(),
    ];

    try {
      $this->appCredentialController($this->app->getAppOwner(), $this->app->getName())
        ->generate($selected_products, $this->app->getAttributes(), $this->app->getCallbackUrl(), []);
      $this->messenger()->addStatus($this->t('New credential generated for the @app app', $args));
    }
    catch (\Exception $exception) {
      $this->messenger()->addError($this->t('Failed to generate credential for the @app app.', $args));
    }

    $form_state->setRedirectUrl($this->getRedirectUrl());
  }

}
