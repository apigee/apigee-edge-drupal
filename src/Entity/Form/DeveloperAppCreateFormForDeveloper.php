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

namespace Drupal\apigee_edge\Entity\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Dedicated form handler that allows a developer to create an developer app.
 */
class DeveloperAppCreateFormForDeveloper extends DeveloperAppCreateFormBase {

  /**
   * The user from the route, captured in buildForm().
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
    // This is the only place where we can grab additional route parameters.
    // See implementation in parent.
    $this->user = $user;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function alterFormBeforeApiProductElement(array &$form, FormStateInterface $form_state): void {
    // The user from the route is the owner.
    $form['owner'] = [
      '#type' => 'value',
      '#value' => $this->user->getEmail(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl(): Url {
    $entity = $this->getEntity();
    return $entity->toUrl('collection-by-developer');
  }

}
