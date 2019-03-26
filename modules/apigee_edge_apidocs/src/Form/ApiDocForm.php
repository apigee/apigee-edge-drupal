<?php

/**
 * Copyright 2019 Google Inc.
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

namespace Drupal\apigee_edge_apidocs\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for API Doc edit forms.
 */
class ApiDocForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $insert = $entity->isNew();

    parent::save($form, $form_state);

    $singular_label = $this->entity->getEntityType()->getSingularLabel();

    if ($insert) {
      $this->messenger()->addMessage($this->t('Created the %label @entity_type_label.', [
        '%label' => $entity->label(),
        '@entity_type_label' => $singular_label,
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('Saved the %label @entity_type_label.', [
        '%label' => $entity->label(),
        '@entity_type_label' => $singular_label,
      ]));
    }
    $form_state->setRedirect('entity.apidoc.collection');
  }

}
