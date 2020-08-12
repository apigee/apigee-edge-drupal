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

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides delete confirmation base form for app API key.
 */
abstract class AppApiKeyDeleteFormBase extends AppApiKeyConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure that you want to delete the API key %key?', [
      '%key' => $this->consumerKey,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_edge_app_api_key_delete_form';
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
      Cache::invalidateTags($this->app->getCacheTags());
      $this->messenger()->addStatus($this->t('API key %key deleted from @app.', $args));
    }
    catch (\Exception $exception) {
      $this->messenger()->addError($this->t('Failed to delete API key %key from @app.', $args));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
