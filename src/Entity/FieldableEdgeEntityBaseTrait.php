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

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Structure\AttributesProperty;
use Drupal\apigee_edge\Exception\EdgeFieldException;
use Drupal\apigee_edge\Plugin\ApigeeFieldStorageFormatInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Trait that allows to make Apigee Edge entities fieldable.
 *
 * Contains implementations that were only available for content entities.
 *
 * A fieldable Edge entity's properties that are being exposed as base fields
 * should not be modified by using the original property setters (inherited
 * from the wrapper SDK entity), those should be modified through the field
 * API because that can keep field and property values in sync.
 *
 * @see \Drupal\Core\Entity\ContentEntityBase
 * @see \Drupal\Core\Entity\FieldableEntityStorageInterface
 * @see \Drupal\apigee_edge\Entity\FieldableEdgeEntityInterface
 */
trait FieldableEdgeEntityBaseTrait {

  use EdgeEntityBaseTrait {
    preSave as private traitPreSave;
    postSave as private traitPostSave;
    __sleep as private traitSleep;
    toArray as private traitToArray;
  }

  /**
   * Local cache of the field definitions.
   *
   * @var array|null
   */
  protected $fieldDefinitions = NULL;

  protected $validationRequired = FALSE;

  /**
   * An array of field item lists, keyed by field definition name.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface[]
   */
  protected $fields = [];

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions() {
    if ($this->fieldDefinitions === NULL) {
      $this->fieldDefinitions = $this->entityManager()->getFieldDefinitions($this->entityTypeId, $this->bundle());
    }
    return $this->fieldDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    $this->fieldDefinitions = NULL;
    return $this->traitSleep();
  }

  /**
   * Parses the properties and its types from the parent class.
   *
   * @return array
   *   The key is the property name, the value is its type, declared in the
   *   docblocks.
   *
   * @throws \ReflectionException
   */
  protected static function getProperties(): array {
    $rc = new \ReflectionClass(parent::class);
    $props = [];
    foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {

      if ($method->getNumberOfParameters() > 0) {
        continue;
      }

      if (strpos($method->getName(), 'get') !== 0) {
        continue;
      }

      $property = lcfirst(substr($method->getName(), 3));
      if (static::isBackListedProperty($property)) {
        continue;
      }
      $props[$property] = static::getFieldType($property);
    }
    return $props;
  }

  /**
   * Array of properties that should not be exposed as base fields.
   *
   * @return array
   *   Array with property names.
   */
  protected static function propertyToFieldBlackList() : array {
    return [];
  }

  /**
   * Returns whether an entity property is blacklisted to be exposed as field.
   *
   * @param string $property
   *   Property name.
   *
   * @return bool
   *   TRUE if it is blacklisted, FALSE otherwise.
   */
  private static function isBackListedProperty(string $property) : bool {
    return in_array($property, static::propertyToFieldBlackList());
  }

  /**
   * Static mapping between entity properties and field types.
   *
   * @return array
   *   An associative array where keys are entity properties and values are
   *   field types.
   */
  protected static function propertyToFieldStaticMap() : array {
    return [];
  }

  /**
   * Returns the type of the field that should represent an entity property.
   *
   * @param string $property
   *   Property name.
   *
   * @return string
   *   Type of the field that should represent an entity property.
   *   Default is string.
   */
  private static function getFieldType(string $property) : string {
    return array_key_exists($property, static::propertyToFieldStaticMap()) ? static::propertyToFieldStaticMap()[$property] : 'string';
  }

  /**
   * Attempts to create a base field definition from a type.
   *
   * @param string $name
   *   Name of the base field.
   * @param string $type
   *   Type of the property.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition|null
   *   Base field definition if found, null otherwise.
   */
  protected static function getBaseFieldDefinition(string $name, string $type): ? BaseFieldDefinition {
    $label = ucwords(preg_replace('/([a-z])([A-Z])/', '$1 $2', $name));
    $is_array = strpos($type, 'list_') === 0;

    try {
      $definition = BaseFieldDefinition::create($type);
    }
    catch (\Exception $ex) {
      // Type not found.
      return NULL;
    }

    $definition->setLabel(t($label));
    $definition->setCardinality($is_array ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1);
    $definition->setTranslatable(FALSE);
    $definition->setDisplayConfigurable('view', TRUE);
    $definition->setDisplayConfigurable('form', TRUE);

    return $definition;
  }

  /**
   * Checks whether an entity type exists.
   *
   * @param string $type
   *   Entity type to test.
   *
   * @return bool
   *   True if the entity type exists, false otherwise.
   */
  protected static function entityTypeExists(string $type): bool {
    try {
      $def = \Drupal::entityTypeManager()->getDefinition($type);
      return !empty($def);
    }
    catch (PluginNotFoundException $ex) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $definitions = [];
    foreach (static::getProperties() as $name => $type) {
      if (($definition = static::getBaseFieldDefinition($name, $type))) {
        $definitions[$name] = $definition;
      }
    }
    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function hasField($field_name) {
    return (bool) $this->getFieldDefinition($field_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition($name) {
    if (!isset($this->fieldDefinitions)) {
      $this->getFieldDefinitions();
    }
    return isset($this->fieldDefinitions[$name]) ?
      $this->fieldDefinitions[$name] :
      NULL;
  }

  /**
   * Returns the field UI's field name prefix.
   *
   * @return string
   *   Prefix of the field.
   */
  private function getFieldPrefix(): string {
    return (string) \Drupal::config('field_ui.settings')->get('field_prefix');
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
    $prefix = $this->getFieldPrefix();
    return strpos($attribute_name, $prefix) === 0 ?
      $attribute_name :
      $prefix . $attribute_name;
  }

  /**
   * Returns the original (stored in SDK Entity) data from the field.
   *
   * @param string $field_name
   *   Machine name of a field.
   *
   * @return mixed|null
   *   Value of a field from the mapped Edge attribute.
   */
  protected function getOriginalFieldData(string $field_name) {
    $getter = 'get' . ucfirst($field_name);
    if (method_exists($this, $getter)) {
      return call_user_func([$this, $getter]);
    }

    return $this->getFieldValueFromAttribute($field_name, $this->getAttributes());
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValueFromAttribute(string $field_name, AttributesProperty $attributes) {
    $attribute_name = $this->getAttributeName($field_name);
    if ($attributes->has($attribute_name)) {
      $attribute_value = $attributes->getValue($attribute_name);
      if (($formatter = $this->findAttributeStorageFormatter($field_name))) {
        return $formatter->decode($attribute_value);
      }
    }
    return NULL;
  }

  /**
   * Finds the storage formatter that is appropriate for a given field.
   *
   * @param string $field_name
   *   Name of the field to look up the plugin for.
   *
   * @return \Drupal\apigee_edge\Plugin\ApigeeFieldStorageFormatInterface|null
   *   Null if not found.
   */
  protected function findAttributeStorageFormatter(string $field_name): ?ApigeeFieldStorageFormatInterface {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $bundle_fields */
    $bundle_fields = self::getFieldDefinitions();
    if (!isset($bundle_fields[$field_name])) {
      return NULL;
    }

    $type = $bundle_fields[$field_name]->getType();

    /** @var \Drupal\apigee_edge\FieldStorageFormatManager $format_manager */
    $format_manager = \Drupal::service('plugin.manager.apigee_field_storage_format');
    return $format_manager->lookupPluginForFieldType($type);
  }

  /**
   * {@inheritdoc}
   */
  public function get($field_name): FieldItemListInterface {
    if (empty($this->fields[$field_name])) {
      $value = $this->getOriginalFieldData($field_name);
      $definitions = $this->getFieldDefinitions();

      if (!isset($definitions[$field_name])) {
        $field_name = $this->getFieldName($field_name);
      }

      if (isset($value) && array_key_exists($field_name, static::propertyToFieldStaticMap()) && static::getFieldType($field_name) === 'timestamp') {
        if (is_array($value)) {
          $value = array_map(function ($item) {
            /** @var \DateTimeImmutable $item */
            return $item->getTimestamp();
          }, $value);
        }
        else {
          /** @var \DateTimeImmutable $value */
          $value = $value->getTimestamp();
        }
      }

      /** @var \Drupal\Core\Field\FieldTypePluginManagerInterface $manager */
      $manager = \Drupal::service('plugin.manager.field.field_type');
      $this->fields[$field_name] = $manager->createFieldItemList($this, $field_name, $value);
    }

    return $this->fields[$field_name];
  }

  /**
   * {@inheritdoc}
   */
  public function set($field_name, $value, $notify = TRUE) {
    // Do not try to set values of fields that does not exists.
    // Also blacklisted properties does not have a field in Drupal and their
    // value changes should not be saved on entity properties either.
    if (!$this->hasField($field_name) || static::isBackListedProperty($this->getAttributeName($field_name))) {
      return $this;
    }

    // Value that is compatible with what a mapped base field can accept.
    $fieldValue = $value;
    if (is_object($value)) {
      // Take care of timestamp fields that value from the SDK is a
      // date object.
      if (array_key_exists($field_name, static::propertyToFieldStaticMap()) && static::getFieldType($field_name) === 'timestamp') {
        /** @var \DateTimeImmutable $value */
        $fieldValue = $value->getTimestamp();
      }
      else {
        $fieldValue = (string) $value;
      }
    }

    // If a base field's cardinality is 1, it means that the
    // underlying entity property (inherited from the wrapped SDK entity)
    // only accepts a scalar value. However, some base fields returns its
    // value as an array. This is what we need to fix here.
    // (We do not change the structure of values that does not belong to
    // base fields).
    if (is_array($value) && $this->getFieldDefinition($field_name) instanceof BaseFieldDefinition && $this->getFieldDefinition($field_name)->getCardinality() === 1) {
      $exists = FALSE;
      $value = NestedArray::getValue($value, ['0', 'value'], $exists);
      if (!$exists) {
        $value = NestedArray::getValue($value, ['value'], $exists);
        if (!$exists) {
          // We should know about this.
          throw new EdgeFieldException(sprintf('Unable to retrieve value of %s base field on %s.', $field_name, get_called_class()));
        }
      }
    }
    $this->get($field_name)->setValue($fieldValue, $notify);
    // Save field's value to the its related property (if there is one).
    $this->setPropertyValue($field_name, $value);
    // If there is no property setter found for the field then save field's
    // value as an attribute. (In that case setPropertyValue() above did not
    // find a property for sure.)
    $setter = 'set' . ucfirst($field_name);
    if (!method_exists($this, $setter)) {
      $attribute_name = $this->getAttributeName($field_name);
      if (($formatter = $this->findAttributeStorageFormatter($field_name))) {
        $this->attributes->add($attribute_name, $formatter->encode($value));
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if ($this->validationRequired && !$this->validated) {
      throw new \LogicException('Entity validation was skipped.');
    }
    else {
      $this->validated = FALSE;
    }

    $this->traitPreSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    $this->traitPostSave($storage, $update);

    // Cleans stale data from the field instance cache.
    // If edge updates a property, then the updated property won't be copied
    // into the field instance cache.
    $this->fields = [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($include_computed = TRUE) {
    $fields = [];
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
    foreach ($this->getFieldDefinitions() as $name => $definition) {
      if ($include_computed || !$definition->isComputed()) {
        $fields[$name] = $this->get($name);
      }
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatableFields($include_computed = TRUE) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($field_name) {
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
  }

  /**
   * {@inheritdoc}
   */
  public function isValidationRequired() {
    return $this->validationRequired;
  }

  /**
   * {@inheritdoc}
   */
  public function setValidationRequired($required) {
    $this->validationRequired = (bool) $required;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    return new \ArrayIterator($this->getFields());
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    $values = [];

    foreach ($this->getFields() as $name => $property) {
      /** @var \Drupal\Core\Field\FieldItemListInterface $property */
      $values[$name] = $property->value;
    }

    // Keep the original values and only add new values from fields.
    // This order is important because for example the array from traitToArray
    // contains date properties as proper DateTimeImmutable objects but the new
    // one contains them as timestamps. Because this function called by
    // DrupalEntityControllerAwareTrait::convertToSdkEntity() that missmatch
    // could cause errors.
    return array_merge($values, $this->traitToArray());
  }

  /**
   * {@inheritdoc}
   *
   * This is a workaround to avoid a fatal error coming from the editor module.
   *
   * The editor module assumes that if an entity implements the
   * FieldableEntityInterface, then it must be a revisionable, like a content
   * entity, so it calls the getRevisionId(), and fails with a fatal error.
   *
   * @see https://www.drupal.org/project/drupal/issues/2942529
   */
  public function getRevisionId() {
    return NULL;
  }

  /**
   * Prevents "Call to undefined method" error.
   *
   * The quickedit core module calls this function in
   * quickedit_entity_view_alter() because the entity view
   * controller is an instance of the EntitViewController class.
   *
   * @return bool
   *   FALSE return value Prevents quickedit core module
   *   from modifying the field structure in quickedit_preprocess_field().
   *
   * @see quickedit_entity_view_alter()
   * @see quickedit_preprocess_field()
   */
  public function isLatestRevision() {
    return FALSE;
  }

}
