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

namespace Drupal\apigee_edge_teams\Entity\Form;

use Drupal\apigee_edge\Entity\Form\EdgeEntityDeleteForm;

/**
 * General form handler for the team delete forms.
 */
class TeamDeleteForm extends EdgeEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  protected function verificationCodeErrorMessage() {
    return $this->t('The name does not match the @entity you are attempting to delete.', [
      '@entity' => mb_strtolower($this->entityTypeManager->getDefinition($this->getEntity()->getEntityTypeId())->getSingularLabel()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    $entity = $this->getEntity();
    if ($entity->hasLinkTemplate('collection-by-team')) {
      return $entity->toUrl('collection-by-team');
    }
    return parent::getRedirectUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $original = parent::getDescription();

    return $this->t('<strong>All apps, credentials and @team membership information will be deleted.</strong> @original', [
      '@original' => $original,
      '@team' => mb_strtolower($this->entityTypeManager->getDefinition($this->entity->getEntityTypeId())->getSingularLabel()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->getRedirectUrl();
  }

}
