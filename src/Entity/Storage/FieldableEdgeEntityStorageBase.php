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

namespace Drupal\apigee_edge\Entity\Storage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Adds fields support to entities without making them content entities.
 *
 * @see \Drupal\Core\Entity\ContentEntityStorageBase
 */
abstract class FieldableEdgeEntityStorageBase extends EdgeEntityStorageBase implements FieldableEdgeEntityStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function purgeFieldData(FieldDefinitionInterface $field_definition, $batch_size) {
    // TODO: Implement purgeFieldData() method.
  }

  /**
   * {@inheritdoc}
   */
  public function finalizePurge(FieldStorageDefinitionInterface $storage_definition) {
    // TODO: Implement finalizePurge() method.
  }

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
  public function onEntityTypeCreate(EntityTypeInterface $entity_type) {}

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeUpdate(EntityTypeInterface $entity_type, EntityTypeInterface $original) {}

  /**
   * {@inheritdoc}
   */
  public function onEntityTypeDelete(EntityTypeInterface $entity_type) {}

  /**
   * {@inheritdoc}
   */
  public function onFieldDefinitionCreate(FieldDefinitionInterface $field_definition) {}

  /**
   * {@inheritdoc}
   */
  public function onFieldDefinitionUpdate(FieldDefinitionInterface $field_definition, FieldDefinitionInterface $original) {}

  /**
   * {@inheritdoc}
   */
  public function onFieldDefinitionDelete(FieldDefinitionInterface $field_definition) {}

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionCreate(FieldStorageDefinitionInterface $storage_definition) {}

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionUpdate(FieldStorageDefinitionInterface $storage_definition, FieldStorageDefinitionInterface $original) {}

  /**
   * {@inheritdoc}
   */
  public function onFieldStorageDefinitionDelete(FieldStorageDefinitionInterface $storage_definition) {}

  /**
   * {@inheritdoc}
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition */
    $count = 0;
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp[] $entities */
    $entities = $this->loadMultiple();
    foreach ($entities as $entity) {
      if ($entity->getAttributeValue($storage_definition->getName()) !== NULL) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPersistentCacheTags(EntityInterface $entity) {
    $cacheTags = parent::getPersistentCacheTags($entity);
    $cacheTags[] = 'entity_field_info';
    return $cacheTags;
  }

  /**
   * {@inheritdoc}
   */
  protected function doCreate(array $values) {
    // Our entities does not support bundles so we removed that part.
    $entity = new $this->entityClass([], $this->entityTypeId);
    $this->initFieldValues($entity, $values);
    return $entity;
  }

  /**
   * Initializes field values.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   An entity object.
   * @param array $values
   *   (optional) An associative array of initial field values keyed by field
   *   name. If none is provided default values will be applied.
   * @param array $field_names
   *   (optional) An associative array of field names to be initialized. If none
   *   is provided all fields will be initialized.
   */
  protected function initFieldValues(FieldableEntityInterface $entity, array $values = [], array $field_names = []) {
    /** @var \Drupal\apigee_edge\Entity\FieldableEdgeEntityInterface $entity */
    // Populate field_name values.
    foreach ($entity->getFieldDefinitions() as $field_name => $def) {
      if (!$field_names || isset($field_names[$field_name])) {
        $value = NULL;

        if (isset($values[$field_name])) {
          $value = $values[$field_name];
        }
        elseif (array_key_exists('attributes', $values)) {
          $value = $entity->getFieldValueFromAttribute($field_name, $values['attributes']);
        }

        if ($value === NULL) {
          $entity->get($field_name)->applyDefaultValue();
          // Apply default value on the property too.
          $entity->setPropertyValue($field_name, $entity->get($field_name)->value);
        }
        else {
          // This call also updates the entity property's value.
          // @see Drupal\apigee_edge\Entity\FieldableEdgeEntityBaseTrait::set()
          $entity->set($field_name, $value);
          unset($values[$field_name]);
        }
      }
    }

    // Set any passed values for non-exposed properties as fields also.
    foreach ($values as $field_name => $value) {
      $entity->setPropertyValue($field_name, $value);
    }

    // Make sure modules can alter field_name initial values.
    $this->invokeHook('field_values_init', $entity);
  }

}
