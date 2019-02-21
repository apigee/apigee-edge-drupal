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

namespace Drupal\apigee_edge\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for configuring base fields on Apigee Edge entities.
 */
abstract class BaseFieldConfigFromBase extends FormBase {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * BaseFieldConfigFromBase constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $base_fields = $this->entityFieldManager->getBaseFieldDefinitions($this->entityType());

    $form['#attached']['library'][] = 'apigee_edge/apigee_edge.admin';

    $form['table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Base field settings'),
      '#header' => [
        $this->t('Field name'),
        $this->t('Required'),
      ],
    ];

    foreach ($base_fields as $name => $base_field) {
      if ($base_field->isDisplayConfigurable('form')) {
        $form['table'][$name] = [
          'name' => [
            '#type' => 'item',
            '#markup' => $base_field->getLabel(),
          ],
          'required' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Required'),
            '#title_display' => 'invisible',
            '#default_value' => $base_field->isRequired(),
          ],
        ];
      }
    }

    foreach ($this->getLockedBaseFields() as $locked) {
      $form['table'][$locked]['required']['#disabled'] = TRUE;
    }

    $form['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $display */
    $display = $this->entityTypeManager->getStorage('entity_form_display')->load("{$this->entityType()}.{$this->entityType()}.default");
    if ($display) {
      foreach ($form_state->getValue('table') as $name => $data) {
        $component = $display->getComponent($name);
        if ($data['required'] && !($component && $component['region'] !== 'hidden')) {
          $form_state->setError($form['table'][$name]['required'], $this->t('%field-name is hidden on the default form display.', [
            '%field-name' => $form['table'][$name]['name']['#markup'],
          ]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $required_base_fields = [];
    foreach ($form_state->getValue('table') as $name => $data) {
      if ($data['required']) {
        $required_base_fields[] = $name;
      }
    }

    $this->saveRequiredBaseFields($required_base_fields);
    // Let's clear every cache not just base field definitions for safety.
    drupal_flush_all_caches();

    $this->messenger()->addStatus($this->t('Field settings have been saved successfully.'));
  }

  /**
   * The name of the entity type in Drupal.
   *
   * @return string
   *   The entity type id.
   */
  abstract protected function entityType(): string;

  /**
   * Returns the array of locked base field on the entity type.
   *
   * @return array
   *   Array of base field names.
   */
  abstract protected function getLockedBaseFields(): array;

  /**
   * Saves required base fields.
   *
   * @param array $required_base_fields
   *   Array of base field names.
   */
  abstract protected function saveRequiredBaseFields(array $required_base_fields): void;

}
