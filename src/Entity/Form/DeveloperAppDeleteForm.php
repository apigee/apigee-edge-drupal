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

use Drupal\apigee_edge\Entity\DeveloperStatusCheckTrait;
use Drupal\Core\Form\FormStateInterface;

/**
 * General form handler for the developer app delete forms.
 */
class DeveloperAppDeleteForm extends EdgeEntityDeleteForm {

  use DeveloperStatusCheckTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app */
    $developer_app = $this->entity;
    $this->checkDeveloperStatus($developer_app->getOwnerId());
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function verificationCode() {
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app */
    $developer_app = $this->getEntity();
    // Request the name of the app instead of the app id (UUID).
    return $developer_app->getName();
  }

  /**
   * {@inheritdoc}
   */
  protected function verificationCodeErrorMessage() {
    return $this->t('The name does not match the @developer_app you are attempting to delete.', [
      '@developer_app' => $this->entityTypeManager->getDefinition($this->getEntity()->getEntityTypeId())->getLowercaseLabel(),
    ]);
  }

}
