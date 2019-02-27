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

namespace Drupal\apigee_edge\Plugin\Field\FieldFormatter;

use Drupal\apigee_edge\Element\StatusPropertyElement;
use Drupal\apigee_edge\Entity\EdgeEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'status_property' formatter.
 *
 * @FieldFormatter(
 *   id = "status_property",
 *   label = @Translation("Status property"),
 *   description = @Translation("Custom field formatter for Apigee Edge status properties."),
 *   field_types = {
 *     "string",
 *   }
 * )
 */
class StatusPropertyFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      StatusPropertyElement::INDICATOR_STATUS_OK => '',
      StatusPropertyElement::INDICATOR_STATUS_WARNING => '',
      StatusPropertyElement::INDICATOR_STATUS_ERROR => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    foreach ($this->getStatusLabels() as $status => $label) {
      $form[$status] = [
        '#type' => 'textfield',
        '#title' => $this->t('@status_label indicator value', ['@status_label' => $label]),
        '#default_value' => $this->getSetting($status),
      ];
    }
    $form['info'] = [
      '#markup' => $this->t('Use lowercase characters only in status values only.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    foreach ($this->getStatusLabels() as $status => $label) {
      $value = $this->getSetting($status);
      $summary[] = $this->t('@status_label indicator value: @value', [
        '@status_label' => $label,
        '@value' => $value !== '' ? $value : $this->t('N/A'),
      ]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element[] = [
      '#type' => 'status_property',
      '#value' => $items->value,
      '#indicator_status' => array_search(strtolower($items->value), $this->getSettings()) ?: '',
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $entity_type_def = \Drupal::entityTypeManager()->getDefinition($entity_type);
    // This field formatter is only applicable on Apigee Edge module's entities
    // and only on their status properties.
    return $field_definition->getName() === 'status' && in_array(EdgeEntityInterface::class, class_implements($entity_type_def->getOriginalClass()));
  }

  /**
   * Returns labels for indicator statuses.
   *
   * @return array
   *   An associative array where keys are the indicator statuses and values
   *   are their representative human readable (translateable) labels.
   */
  private function getStatusLabels(): array {
    return [
      StatusPropertyElement::INDICATOR_STATUS_OK => $this->t('OK status'),
      StatusPropertyElement::INDICATOR_STATUS_WARNING => $this->t('Warning status'),
      StatusPropertyElement::INDICATOR_STATUS_ERROR => $this->t('Error status'),
    ];
  }

}
