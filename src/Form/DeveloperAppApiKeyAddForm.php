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
use Drupal\apigee_edge\Entity\Form\DeveloperAppFormTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Provides API key add form for developer app.
 */
class DeveloperAppApiKeyAddForm extends AppApiKeyAddFormBase {

  use DeveloperAppFormTrait, DeveloperAppCredentialFormTrait;

  /**
   * The user from route.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AppInterface $app = NULL, ?UserInterface $user = NULL) {
    $this->user = $user;
    return parent::buildForm($form, $form_state, $app);
  }

  /**
   * {@inheritdoc}
   */
  protected function getAppOwner(): string {
    return $this->user->getEmail();
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl(): Url {
    return $this->getCancelUrl();
  }

}
