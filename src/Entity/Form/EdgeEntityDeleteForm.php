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

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default entity delete form implementation for Apigee Edge entities.
 *
 * It requires from the user to type the "id" of the entity to confirm removal.
 */
class EdgeEntityDeleteForm extends EntityDeleteForm {

  /**
   * EdgeEntityDeleteForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    // Ensure the entity type manager is always initialized.
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['verification_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type "%verification_code" to proceed', [
        '%verification_code' => $this->verificationCode(),
      ]),
      '#default_value' => '',
      '#required' => TRUE,
      '#element_validate' => [
        '::validateVerificationCode',
      ],
    ];

    return $form;
  }

  /**
   * Element validate callback for the verification code form element.
   *
   * @param array $element
   *   Element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   The complete form.
   */
  final public function validateVerificationCode(array &$element, FormStateInterface $form_state, array &$complete_form) {
    if ($element['#value'] !== $this->verificationCode()) {
      $form_state->setError($element, $this->verificationCodeErrorMessage());
    }
  }

  /**
   * Returns the verification code that the user should provide to confirm.
   *
   * @return string
   *   The verification code.
   */
  protected function verificationCode() {
    // By default this it the entity's default entity id.
    return $this->getEntity()->id();
  }

  /**
   * The error message that the user should see when verification fails.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The error message to be displayed to the user.
   */
  protected function verificationCodeErrorMessage() {
    return $this->t('The provided text does not match the id of @entity that you are attempting to delete.', [
      '@entity' => mb_strtolower($this->entityTypeManager->getDefinition($this->getEntity()->getEntityTypeId())->getSingularLabel()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the %label @entity-type?', [
      '@entity-type' => mb_strtolower($this->getEntity()->getEntityType()->getSingularLabel()),
      '%label' => $this->getEntity()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    return $this->t('The %label @entity-type has been deleted.', [
      '@entity-type' => mb_strtolower($this->getEntity()->getEntityType()->getSingularLabel()),
      '%label' => $this->getEntity()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    // \Drupal\Core\Entity\EntityForm::buildEntity() would call set() on
    // $entity that only exists on config and content entities.
    // @see \Drupal\Core\Entity\EntityForm::copyFormValuesToEntity()
    return $this->entity;
  }

}
