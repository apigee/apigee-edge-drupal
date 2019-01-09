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

/**
 * General form handler for developer/team (company) app delete forms.
 */
class AppDeleteForm extends EdgeEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  protected function verificationCodeErrorMessage() {
    return $this->t('The name does not match the @app you are attempting to delete.', [
      '@app' => $this->entityTypeManager->getDefinition($this->getEntity()->getEntityTypeId())->getLowercaseLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function verificationCode() {
    /** @var \Drupal\apigee_edge\Entity\AppInterface $app */
    $app = $this->getEntity();
    // Request the name of the app instead of the app id (UUID).
    return $app->getName();
  }

}
