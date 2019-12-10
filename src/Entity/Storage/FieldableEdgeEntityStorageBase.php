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

namespace Drupal\apigee_edge\Entity\Storage;

use Drupal\apigee_edge\Entity\FieldableEdgeEntityInterface;
use Drupal\apigee_edge\Exception\InvalidArgumentException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Base class for fieldable Apigee Edge entities.
 */
abstract class FieldableEdgeEntityStorageBase extends EdgeEntityStorageBase implements FieldableEdgeEntityStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function requiresFieldStorageSchemaChanges(FieldStorageDefinitionInterface $storage_definition, FieldStorageDefinitionInterface $original) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresFieldDataMigration(FieldStorageDefinitionInterface $storage_definition, FieldStorageDefinitionInterface $original) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresEntityStorageSchemaChanges(EntityTypeInterface $entity_type, EntityTypeInterface $original) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresEntityDataMigration(EntityTypeInterface $entity_type, EntityTypeInterface $original) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeCreate(EntityTypeInterface $entity_type) {
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeUpdate(EntityTypeInterface $entity_type, EntityTypeInterface $original) {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldableEntityTypeCreate(EntityTypeInterface $entity_type, array $field_storage_definitions) {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldableEntityTypeUpdate(EntityTypeInterface $entity_type, EntityTypeInterface $original, array $field_storage_definitions, array $original_field_storage_definitions, array &$sandbox = NULL) {
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeDelete(EntityTypeInterface $entity_type) {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldDefinitionCreate(FieldDefinitionInterface $field_definition) {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldDefinitionUpdate(FieldDefinitionInterface $field_definition, FieldDefinitionInterface $original) {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldDefinitionDelete(FieldDefinitionInterface $field_definition) {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionCreate(FieldStorageDefinitionInterface $storage_definition) {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionUpdate(FieldStorageDefinitionInterface $storage_definition, FieldStorageDefinitionInterface $original) {
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionDelete(FieldStorageDefinitionInterface $storage_definition) {
  }

  /**
   * {@inheritdoc}
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    return $as_bool ? FALSE : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function purgeFieldData(FieldDefinitionInterface $field_definition, $batch_size) {
    // Should we $this->countFieldData($field_definition); instead?
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function finalizePurge(FieldStorageDefinitionInterface $storage_definition) {
  }

  /**
   * {@inheritdoc}
   */
  protected function getPersistentCacheTags(EntityInterface $entity) {
    $tags = parent::getPersistentCacheTags($entity);
    $tags[] = 'entity_field_info';
    return $tags;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\ContentEntityStorageBase::doCreate()
   */
  protected function doCreate(array $values) {
    /** @var \Drupal\apigee_edge\Entity\FieldableEdgeEntityInterface $entity */
    $entity = parent::doCreate($values);
    $this->initFieldValues($entity, $values);
    return $entity;
  }

  /**
   * Initializes field values.
   *
   * @param \Drupal\apigee_edge\Entity\FieldableEdgeEntityInterface $entity
   *   An entity object.
   * @param array $values
   *   (optional) An associative array of initial field values keyed by field
   *   name. If none is provided default values will be applied.
   * @param array $field_names
   *   (optional) An associative array of field names to be initialized. If none
   *   is provided all fields will be initialized.
   *
   * @see \Drupal\Core\Entity\ContentEntityStorageBase::initFieldValues()
   */
  protected function initFieldValues(FieldableEdgeEntityInterface $entity, array $values = [], array $field_names = []) {
    // Populate field values.
    foreach ($entity as $name => $field) {
      if (!$field_names || isset($field_names[$name])) {
        if (isset($values[$name])) {
          $entity->set($name, $values[$name]);
        }
        elseif (!array_key_exists($name, $values)) {
          $entity->get($name)->applyDefaultValue();
        }
      }
      unset($values[$name]);
    }

    // Set any passed values for non-defined fields also.
    foreach ($values as $name => $value) {
      try {
        $entity->setPropertyValue($name, $value);
      }
      catch (InvalidArgumentException $exception) {
        // Property not found, which could be fine.
      }
    }

    // Make sure modules can alter field initial values.
    $this->invokeHook('field_values_init', $entity);
  }

}
