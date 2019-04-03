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

namespace Drupal\apigee_edge\Entity;

use Drupal\apigee_edge\Exception\InvalidArgumentException;
use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Base field support for Apigee Entities without making them content entities.
 *
 * We borrowed some goodies from Drupal\Core\Entity\ContentEntityBase here, but
 * only what was really necessary.
 *
 * @see \Drupal\Core\Entity\ContentEntityBase
 */
abstract class FieldableEdgeEntityBase extends EdgeEntityBase implements FieldableEdgeEntityInterface {

  // The majority of Drupal core & contrib assumes that if an entity is
  // fieldable then it must be a content entity and because it is content entity
  // it also must support revisioning. This incorrect assumption justifies the
  // reason why this is here.
  use RevisioningWorkaroundTrait;

  /**
   * Whether entity validation is required before saving the entity.
   *
   * @var bool
   */
  protected $validationRequired = FALSE;

  /**
   * Local cache for field definitions.
   *
   * @var array|null
   *
   * @see \Drupal\apigee_edge\Entity\FieldableEdgeEntityBase::getFieldDefinitions()
   */
  protected $fieldDefinitions;

  /**
   * Local cache for for fields.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface[]
   *
   * @see \Drupal\apigee_edge\Entity\FieldableEdgeEntityBase::get()
   */
  protected $fields = [];

  /**
   * Whether entity validation was performed.
   *
   * @var bool
   */
  protected $validated = FALSE;

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    $this->fields = [];
    $this->fieldDefinitions = NULL;
    return parent::__sleep();
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
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $definitions = [];
    // Expose non-blacklisted properties from decorated
    // entity object as base fields by default.
    foreach (static::getProperties() as $name => $type) {
      if (($definition = static::getBaseFieldDefinition($name, $type))) {
        $definitions[$name] = $definition;
      }
    }
    return $definitions;
  }

  /**
   * Parses the properties and its types from the parent class.
   *
   * @return array
   *   The key is the property name, the value is its type, declared in the
   *   docblocks.
   */
  protected static function getProperties(): array {
    $props = [];
    try {
      $rc = new \ReflectionClass(static::decoratedClass());
      foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
        // This is not a property getter rather a utility function.
        if ($method->getNumberOfParameters() > 0) {
          continue;
        }
        // Find property getters on decorated PHP API Client entity classes.
        if (strpos($method->getName(), 'get') === 0) {
          $property = lcfirst(substr($method->getName(), 3));
        }
        elseif (strpos($method->getName(), 'is') === 0) {
          $property = lcfirst(substr($method->getName(), 2));
        }
        else {
          continue;
        }

        if (static::exposePropertyAsBaseField($property)) {
          continue;
        }
        $props[$property] = static::propertyFieldType($property);
      }
    }
    catch (\ReflectionException $e) {
    }
    return $props;
  }

  /**
   * Array of properties that should not be exposed as base fields by default.
   *
   * @return string[]
   *   Array with property names.
   */
  protected static function propertyToBaseFieldBlackList(): array {
    return [
      // We expose each attribute as a field.
      'attributes',
      // Do not expose the organization user (used in Drupal) who created the
      // entity. (These properties are generally available on Management API
      // entities this is the reason why we blacklisted them in this base
      // class).
      'createdBy',
      'lastModifiedBy',
    ];
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
  private static function exposePropertyAsBaseField(string $property): bool {
    return in_array($property, static::propertyToBaseFieldBlackList());
  }

  /**
   * Static mapping between entity properties and Drupal field types.
   *
   * @return array
   *   An associative array where keys are entity properties and values are
   *   destination Drupal field types.
   */
  protected static function propertyToBaseFieldTypeMap(): array {
    return [
        // We do not want Drupal to apply default values
        // on these properties if they are empty therefore their field type is
        // a simple "timestamp" instead of "created" or "changed".
        // (These properties are generally available on Management API
        // entities this is the reason why we defined them in this base
        // class).
      'createdAt' => 'timestamp',
      'lastModifiedAt' => 'timestamp',
    ];
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
  private static function propertyFieldType(string $property): string {
    return array_key_exists($property, static::propertyToBaseFieldTypeMap()) ? static::propertyToBaseFieldTypeMap()[$property] : 'string';
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
  protected static function getBaseFieldDefinition(string $name, string $type): ?BaseFieldDefinition {
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
   * {@inheritdoc}
   */
  public function get($field_name) {
    if (!isset($this->fields[$field_name])) {
      $value = $this->getFieldValue($field_name);

      // Here field name equals the property name.
      if ($value !== NULL) {
        // Fix field value of a timestamp property field.
        if (static::propertyFieldType($field_name) === 'timestamp') {
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
      }

      // Based on \Drupal\Core\Entity\ContentEntityBase::getTranslatedField().
      /** @var \Drupal\Core\Field\FieldTypePluginManagerInterface $manager */
      $manager = \Drupal::service('plugin.manager.field.field_type');
      $this->fields[$field_name] = $manager->createFieldItemList($this, $field_name, $value);
    }

    return $this->fields[$field_name];
  }

  /**
   * Returns the field value from the current object.
   *
   * @param string $field_name
   *   Machine name of a field.
   *
   * @return mixed|null
   *   Value of a field from current object, or null if it does exits.
   */
  protected function getFieldValue(string $field_name) {
    // We call the getters on the current object instead of the decorated one
    // because they can return the correct information.
    // Because the current object implements the interface of the decorated
    // object there should be any getter on the decorated object that does not
    // have a decorator in the current class (that potentially also calls to the
    // decorated getter method under the hood.)
    foreach (['get', 'is'] as $prefix) {
      $getter = $prefix . ucfirst($field_name);
      if (method_exists($this, $getter)) {
        return call_user_func([$this, $getter]);
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition($name) {
    if (!isset($this->fieldDefinitions)) {
      $this->getFieldDefinitions();
    }
    if (isset($this->fieldDefinitions[$name])) {
      return $this->fieldDefinitions[$name];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions() {
    if (!isset($this->fieldDefinitions)) {
      $this->fieldDefinitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($this->entityTypeId, $this->bundle());
    }
    return $this->fieldDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($include_computed = TRUE) {
    $fields = [];
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
    $fields = [];
    foreach ($this->getFieldDefinitions() as $name => $definition) {
      if (($include_computed || !$definition->isComputed()) && $definition->isTranslatable()) {
        $fields[$name] = $this->get($name);
      }
    }
    return $fields;
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
  public function validate() {
    $this->validated = TRUE;
    $violations = $this->getTypedData()->validate();
    return new EntityConstraintViolationList($this, iterator_to_array($violations));
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
    $this->validationRequired = $required;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($field_name) {
    $value = $this->convertFieldValueToPropertyValue($field_name);
    // Save field's value to the its related property (if there is one).
    try {
      $this->setPropertyValue($field_name, $value);
    }
    catch (InvalidArgumentException $e) {
      // Property not found, which could be fine.
    }
  }

  /**
   * Converts a field value to a property value.
   *
   * @param string $field_name
   *   Name of a field.
   *
   * @return mixed
   *   Value of a property.
   */
  protected function convertFieldValueToPropertyValue(string $field_name) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $definition */
    $definition = $this->getFieldDefinition($field_name);
    if ($definition->getFieldStorageDefinition()->getCardinality() === 1) {
      $value = $this->get($field_name)->value;
    }
    else {
      // Extract values from multi-value fields the right way. Magic getter
      // would just return the first item from the list.
      // @see \Drupal\Core\Field\FieldItemList::__get()
      $value = [];
      foreach ($this->get($field_name) as $index => $item) {
        $value[$index] = $item->value;
      }
    }

    // Take care of timestamp fields that value in the SDK is a
    // date object.
    if (static::propertyFieldType($field_name) === 'timestamp') {
      /** @var \DateTimeImmutable $value */
      $value = \DateTimeImmutable::createFromFormat('U', $value);
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function set($field_name, $value, $notify = TRUE) {
    // Do not try to set value of a field that does not exist.
    if (!$this->hasField($field_name)) {
      // According to spec an exception should be thrown in this case.
      throw new InvalidArgumentException(sprintf('"%s" field does not exist on "s" entity.', $field_name, get_class($this)));
    }

    // Value that is compatible with what a mapped base field can accept.
    $field_value = $value;
    if (is_object($value)) {
      // Take care of timestamp fields that value from the SDK is a
      // date object.
      if (static::propertyFieldType($field_name) === 'timestamp') {
        /** @var \DateTimeImmutable $value */
        $field_value = $value->getTimestamp();
      }
      else {
        $field_value = (string) $value;
      }
    }

    // Save field's value as a field. This calls onChange() that saves
    // field value to the related property.
    $this->get($field_name)->setValue($field_value, $notify);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setPropertyValue(string $field_name, $value): void {
    // Ignore NULL values, because those are not supported by setters of
    // the SDK entities.
    if ($value === NULL) {
      return;
    }
    // We try to call the setter on the current object first,
    // because it can take care of extra things not just updating the values
    // on the decorated SDK entity.
    $setter = 'set' . ucfirst($field_name);
    $destination = NULL;
    if (method_exists($this, $setter)) {
      $destination = $this;
    }
    elseif (method_exists($this->decorated, $setter)) {
      $destination = $this->decorated;
    }
    if ($destination) {
      try {
        $destination->{$setter}($value);
      }
      catch (\TypeError $error) {
        // Auto-retry, pass the value as variable-length arguments.
        // Ignore empty variable list.
        if (is_array($value)) {
          // Clear the value of the property.
          if (empty($value)) {
            $destination->{$setter}();
          }
          else {
            $destination->{$setter}(...$value);
          }
        }
        else {
          throw $error;
        }
      }
    }
    else {
      throw new InvalidArgumentException("Property with %s name not found.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Cleans stale data from the field instance cache.
    // If edge updates a property, then the updated property won't be copied
    // into the field instance cache.
    $this->fields = [];
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    $values = [];
    foreach ($this->getFields() as $name => $property) {
      $values[$name] = $property->getValue();
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    return new \ArrayIterator($this->getFields());
  }

}
