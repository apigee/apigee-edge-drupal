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

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base entity form for fieldable Apigee Edge entity types.
 *
 * Based on ContentEntityForm.
 *
 * @see \Drupal\Core\Entity\ContentEntityForm
 */
abstract class FieldableEdgeEntityForm extends EntityForm implements FieldableEdgeEntityFormInterface {

  /**
   * The fieldable entity being used by this form.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function init(FormStateInterface $form_state) {
    $form_display = EntityFormDisplay::collectRenderDisplay($this->entity, $this->getOperation());
    $this->setFormDisplay($form_display, $form_state);

    parent::init($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    unset($form['#after_build']);
    $this->getFormDisplay($form_state)->buildForm($this->entity, $form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\ContentEntityForm::buildEntity()
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = parent::buildEntity($form, $form_state);

    // Mark the entity as requiring validation.
    $entity->setValidationRequired(!$form_state->getTemporaryValue('entity_validated'));

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\apigee_edge\Entity\FieldableEdgeEntityInterface $entity */
    // First, extract values from widgets.
    $extracted = $this->getFormDisplay($form_state)->extractFormValues($entity, $form, $form_state);

    // Then extract the values of fields that are not rendered through widgets,
    // by simply copying from top-level form values. This leaves the fields
    // that are not being edited within this form untouched.
    foreach ($form_state->getValues() as $name => $values) {
      if ($entity->hasField($name) && !isset($extracted[$name])) {
        $entity->set($name, $values);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormDisplay(FormStateInterface $form_state) {
    return $form_state->get('form_display');
  }

  /**
   * {@inheritdoc}
   */
  public function setFormDisplay(EntityFormDisplayInterface $form_display, FormStateInterface $form_state) {
    $form_state->set('form_display', $form_display);
    return $this;
  }

}
