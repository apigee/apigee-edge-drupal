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

use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides approve confirmation base form for app credential.
 */
abstract class AppCredentialApproveFormBase extends AppCredentialConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure that you want to approve the credential with consumer key %key?', [
      '%key' => $this->consumerKey,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Approve');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_app_credential_approve_form';
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
      $this->appCredentialController($this->app->getAppOwner(), $this->app->getName())->setStatus($this->consumerKey, AppCredentialControllerInterface::STATUS_APPROVE);
      $this->messenger()->addStatus($this->t('Credential with consumer key %key approved for @app.', $args));
    }
    catch (\Exception $exception) {
      $this->messenger()->addError($this->t('Failed to approve credential with consumer key %key for @app.', $args));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
