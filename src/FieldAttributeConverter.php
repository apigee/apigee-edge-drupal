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

namespace Drupal\apigee_edge;

use Apigee\Edge\Structure\AttributesProperty;
use Drupal\apigee_edge\Plugin\FieldStorageFormatInterface;
use Drupal\apigee_edge\Plugin\FieldStorageFormatManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * Default field-attribute converter service implementation.
 */
final class FieldAttributeConverter implements FieldAttributeConverterInterface {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * The field formatter service.
   *
   * @var \Drupal\apigee_edge\Plugin\FieldStorageFormatManagerInterface
   */
  private $formatManager;

  /**
   * Cached field definitions keyed by entity type.
   *
   * @var array[]\Drupal\Core\Field\FieldDefinitionInterface[]
   */
  private $fieldDefinitions = [];

  /**
   * FieldNameToAttributeNameConverter constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\apigee_edge\Plugin\FieldStorageFormatManagerInterface $format_manager
   *   The field formatter service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config factory service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, FieldStorageFormatManagerInterface $format_manager, ConfigFactoryInterface $config) {
    $this->config = $config;
    $this->entityFieldManager = $entity_field_manager;
    $this->formatManager = $format_manager;
  }

  /**
   * Returns the field UI's field name prefix.
   *
   * @return string
   *   Prefix of the field.
   */
  protected function getFieldPrefix(): string {
    return (string) $this->config->get('field_ui.settings')->get('field_prefix');
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributeName(string $field_name): string {
    $field_prefix = $this->getFieldPrefix();
    if ($field_prefix && strpos($field_name, $field_prefix) === 0) {
      return substr($field_name, strlen($field_prefix));
    }

    return $field_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName(string $attribute_name): string {
    $field_prefix = $this->getFieldPrefix();
    return strpos($attribute_name, $field_prefix) === 0 ? $attribute_name : $field_prefix . $attribute_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValueFromAttribute(string $entity_type, string $field_name, AttributesProperty $attributes) {
    $attribute_name = $this->getAttributeName($field_name);
    if ($attributes->has($attribute_name)) {
      $attribute_value = $attributes->getValue($attribute_name);
      if (($formatter = $this->findFieldStorageFormatter($entity_type, $field_name))) {
        return $formatter->decode($attribute_value);
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributeValueFromField(string $entity_type, string $field_name, $field_value) {
    if (($formatter = $this->findFieldStorageFormatter($entity_type, $field_name))) {
      return $formatter->encode($field_value);
    }

    return NULL;
  }

  /**
   * Finds the storage formatter that is appropriate for a given field.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $field_name
   *   Name of the field to look up the plugin for.
   *
   * @return \Drupal\apigee_edge\Plugin\FieldStorageFormatInterface|null
   *   Null if not found.
   */
  protected function findFieldStorageFormatter(string $entity_type, string $field_name): ?FieldStorageFormatInterface {
    if (!isset($this->fieldDefinitions[$entity_type])) {
      $this->fieldDefinitions[$entity_type] = $this->entityFieldManager->getFieldDefinitions($entity_type, $entity_type);
    }
    if (!isset($this->fieldDefinitions[$entity_type][$field_name])) {
      return NULL;
    }
    $type = $this->fieldDefinitions[$entity_type][$field_name]->getType();
    return $this->formatManager->lookupPluginForFieldType($type);
  }

}
