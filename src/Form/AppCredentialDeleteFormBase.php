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

use Apigee\Edge\Api\Management\Entity\AppCredential;
use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides delete confirmation base form for app credential.
 */
abstract class AppCredentialDeleteFormBase extends ConfirmFormBase {

  /**
   * The app entity.
   *
   * @var \Drupal\apigee_edge\Entity\AppInterface
   */
  protected $app;

  /**
   * The consumer key.
   *
   * @var string
   */
  protected $consumerKey;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure that you want to delete the credential with consumer key %key from the @app app?', [
      '%key' => $this->consumerKey,
      '@app' => $this->app->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_app_credential_delete_form';
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
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AppInterface $app = NULL, ?string $consumer_key = NULL) {
    // Validate consumer key in app credentials.
    if (!in_array($consumer_key, array_map(function (AppCredential $app_credential) {
      return $app_credential->getConsumerKey();
    }, $app->getCredentials()))) {
      throw new NotFoundHttpException();
    }

    $this->app = $app;
    $this->consumerKey = $consumer_key;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $args = [
      '%key' => $this->consumerKey,
      '@app' => $this->app->label(),
    ];

    try {
      $this->appCredentialController($this->app->getAppOwner(), $this->app->getName())->delete($this->consumerKey);
      $this->messenger()->addStatus($this->t('Credential with consumer key %key deleted from the @app app.', $args));
    }
    catch (\Exception $exception) {
      $this->messenger()->addError($this->t('Failed to delete credential with consumer key %key from the @app app.', $args));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
