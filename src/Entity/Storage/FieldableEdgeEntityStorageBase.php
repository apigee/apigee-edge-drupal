<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

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
    /** @var FieldStorageDefinitionInterface $storage_definition */
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
}
