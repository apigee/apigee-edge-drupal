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
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
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
   * TODO Add missing return type-hint in 2.x.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    /** @var \Drupal\apigee_edge\Entity\FieldableEdgeEntityInterface $entity */
    $entity = $this->buildEntity($form, $form_state);

    $violations = $entity->validate();

    // Remove violations of inaccessible fields.
    $violations->filterByFieldAccess($this->currentUser());

    // In case a field-level submit button is clicked, for example the 'Add
    // another item' button for multi-value fields or the 'Upload' button for a
    // File or an Image field, make sure that we only keep violations for that
    // specific field.
    $edited_fields = [];
    if ($limit_validation_errors = $form_state->getLimitValidationErrors()) {
      foreach ($limit_validation_errors as $section) {
        $field_name = reset($section);
        if ($entity->hasField($field_name)) {
          $edited_fields[] = $field_name;
        }
      }
      $edited_fields = array_unique($edited_fields);
    }
    else {
      $edited_fields = $this->getEditedFieldNames($form_state);
    }

    // Remove violations for fields that are not edited.
    $violations->filterByFields(array_diff(array_keys($entity->getFieldDefinitions()), $edited_fields));

    $this->flagViolations($violations, $form, $form_state);

    // The entity was validated.
    $entity->setValidationRequired(FALSE);
    $form_state->setTemporaryValue('entity_validated', TRUE);

    return $entity;
  }

  /**
   * Gets the names of all fields edited in the form.
   *
   * If the entity form customly adds some fields to the form (i.e. without
   * using the form display), it needs to add its fields here and override
   * flagViolations() for displaying the violations.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return string[]
   *   An array of field names.
   *
   * @todo Add missing return type-hint in 2.x.
   */
  protected function getEditedFieldNames(FormStateInterface $form_state) {
    return array_keys($this->getFormDisplay($form_state)->getComponents());
  }

  /**
   * Flags violations for the current form.
   *
   * If the entity form customly adds some fields to the form (i.e. without
   * using the form display), it needs to add its fields to array returned by
   * getEditedFieldNames() and overwrite this method in order to show any
   * violations for those fields; e.g.:
   * @code
   * foreach ($violations->getByField('name') as $violation) {
   *   $form_state->setErrorByName('name', $violation->getMessage());
   * }
   * parent::flagViolations($violations, $form, $form_state);
   * @endcode
   *
   * @param \Drupal\Core\Entity\EntityConstraintViolationListInterface $violations
   *   The violations to flag.
   * @param array $form
   *   A nested array of form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @todo Add missing return type-hint in 2.x.
   */
  protected function flagViolations(EntityConstraintViolationListInterface $violations, array $form, FormStateInterface $form_state) {
    // Flag entity level violations.
    foreach ($violations->getEntityViolations() as $violation) {
      /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
      $form_state->setErrorByName(str_replace('.', '][', $violation->getPropertyPath()), $violation->getMessage());
    }
    // Let the form display flag violations of its fields.
    $this->getFormDisplay($form_state)->flagWidgetsErrorsFromViolations($violations, $form, $form_state);
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
