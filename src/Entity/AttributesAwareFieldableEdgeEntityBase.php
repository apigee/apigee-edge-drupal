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

use Apigee\Edge\Entity\EntityInterface;
use Apigee\Edge\Entity\Property\AttributesPropertyInterface;
use Drupal\apigee_edge\Exception\InvalidArgumentException;
use Drupal\apigee_edge\FieldAttributeConverterInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * For fieldable Edge entities that can use attributes as field storage.
 */
abstract class AttributesAwareFieldableEdgeEntityBase extends FieldableEdgeEntityBase implements AttributesAwareFieldableEdgeEntityBaseInterface {

  /**
   * The decorated SDK entity.
   *
   * @var \Apigee\Edge\Entity\EntityInterface|\Apigee\Edge\Entity\Property\AttributesPropertyInterface
   */
  protected $decorated;

  /**
   * AttributesAwareFieldableEntityBase constructor.
   *
   * @param array $values
   *   An array of values to set, keyed by property name.
   * @param null|string $entity_type
   *   Type of the entity. It is optional because constructor sets its default
   *   value.
   * @param \Apigee\Edge\Entity\EntityInterface|null $decorated
   *   The SDK entity that this Drupal entity decorates.
   */
  public function __construct(array $values, string $entity_type, ?EntityInterface $decorated = NULL) {
    parent::__construct($values, $entity_type, $decorated);
    if (!$this->decorated instanceof AttributesPropertyInterface) {
      throw new InvalidArgumentException(sprintf('Decorated SDK entity must be instance of %s interface, got %s.', AttributesPropertyInterface::class, get_class($decorated)));
    }
  }

  /**
   * Returns the field-attribute converter service.
   *
   * @return \Drupal\apigee_edge\FieldAttributeConverterInterface
   *   Field attribute convert service.
   */
  protected function fieldAttributeConverter(): FieldAttributeConverterInterface {
    return \Drupal::service('apigee_edge.converter.field_attribute');
  }

  /**
   * {@inheritdoc}
   */
  public function get($field_name) {
    $definition = $this->getFieldDefinition($field_name);
    // No field found with this name.
    if ($definition === NULL) {
      return NULL;
    }
    // Ignore base fields, because their value should be stored in entity
    // properties.
    if ($definition instanceof BaseFieldDefinition) {
      return parent::get($field_name);
    }
    if (!isset($this->fields[$field_name])) {
      /** @var \Drupal\field\Entity\FieldConfig $definition */
      // Otherwise let's try to get the value of a field from an attribute
      // on the decorated entity.
      $value = $this->fieldAttributeConverter()->getFieldValueFromAttribute($this->entityTypeId, $field_name, $this->decorated->getAttributes());
      // Based on \Drupal\Core\Entity\ContentEntityBase::getTranslatedField().
      /** @var \Drupal\Core\Field\FieldTypePluginManagerInterface $manager */
      $manager = \Drupal::service('plugin.manager.field.field_type');
      $this->fields[$field_name] = $manager->createFieldItemList($this, $field_name, $value);
    }

    return $this->fields[$field_name];
  }

  /**
   * {@inheritdoc}
   */
  public function setPropertyValue(string $field_name, $value): void {
    // If value is null, parent setPropertyValue() is going to ignore it
    // because SDK entity's simple property setters does not support parameters
    // with null value. But if field is not a base field then we have to clear
    // its value.
    if ($value === NULL && !$this->getFieldDefinition($field_name) instanceof BaseFieldDefinition) {
      $this->setAttributeValueFromField($field_name);
    }
    else {
      try {
        parent::setPropertyValue($field_name, $value);
      }
      catch (InvalidArgumentException $e) {
        // Property not found for the field, let's try to save field's value
        // as an attribute.
        $this->setAttributeValueFromField($field_name);
      }
    }
  }

  /**
   * Sets attribute value from a field.
   *
   * @param string $field_name
   *   Name of a field, which must not be a base field.
   */
  private function setAttributeValueFromField(string $field_name) {
    // We need to unaltered field data value here not the field value returned
    // by $this->get($field_name)->value (magic getter).
    $field_value = $this->get($field_name)->getValue();
    // Property not found so let's save it as an attribute value.
    $attribute_value = $this->fieldAttributeConverter()->getAttributeValueFromField($this->entityTypeId, $field_name, $field_value);
    if ($attribute_value !== NULL) {
      $attribute_name = $this->fieldAttributeConverter()->getAttributeName($field_name);
      // Do not leave empty attributes. If generated attribute value is an
      // empty string let's remove it from the entity.
      // (Apigee Edge MGMT UI does not allow to save an entity with empty
      // attribute value, the API does.)
      if ($attribute_value === '') {
        $this->decorated->deleteAttribute($attribute_name);
      }
      else {
        $this->decorated->setAttribute($attribute_name, $attribute_value);
      }
    }
  }

}
