<?php

namespace Drupal\apigee_edge\Entity;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

const EDGE_ENTITY_NAMESPACE = '\Apigee\Edge\Api\Management\Entity';

trait FieldableEdgeEntityBaseTrait {
  use EdgeEntityBaseTrait {
    preSave as private traitPreSave;
  }

  /**
   * Local cache of the field definitions.
   *
   * @var array|null
   */
  protected $fieldDefinitions = NULL;

  protected $validationRequired = FALSE;

  /**
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

  public function __sleep() {
    $this->fieldDefinitions = NULL;
  }

  protected static function getProperties(): array {
    $rc = new \ReflectionClass(parent::class);
    $props = [];
    foreach ($rc->getProperties() as $property) {
      $matches = [];
      $type = 'mixed';
      if (preg_match('/@var[\s]+([\S]+)/', $property->getDocComment(), $matches)) {
        $type = $matches[1];
      }
      $props[$property->getName()] = $type;
    }
    return $props;
  }

  protected static function getBaseFieldDefinition(string $name, string $type): ?BaseFieldDefinition {
    static $typeMapping = [
      'string' => 'string',
    ];
    $label = ucfirst($name);
    if (($is_array = strpos($type, '[]') === strlen($type) - 2)) {
      $type = substr($type, 0, -2);
    }

    $definition = NULL;
    if (isset($typeMapping[$type])) {
      $type = $typeMapping[$type];
      $definition = BaseFieldDefinition::create($type);
    }
    elseif (strpos($type, EDGE_ENTITY_NAMESPACE) === 0) {
      $definition = BaseFieldDefinition::create('entity_reference');
      $target = substr($type, strlen(EDGE_ENTITY_NAMESPACE) + 1);
      if (static::entityTypeExists($target)) {
        $definition->setSettings([
          'target_type' => $target,
        ]);
      }
    }

    if (!$definition) {
      return NULL;
    }

    $definition->setLabel(t($label));
    $definition->setCardinality($is_array ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1);
    $definition->setTranslatable(FALSE);
    $definition->setDisplayConfigurable('view', TRUE);
    $definition->setDisplayConfigurable('form', TRUE);

    return $definition;
  }

  protected static function entityTypeExists(string $type): bool {
    try {
      $def = \Drupal::entityTypeManager()->getDefinition($type);
      return !empty($def);
    }
    catch (PluginNotFoundException $ex) {
      return false;
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

  protected function getFieldPrefix(): string {
    return \Drupal::config('field_ui.settings')->get('field_prefix');
  }

  protected function getAttributeName(string $field_name): string {
    $field_prefix = $this->getFieldPrefix();
    if ($field_prefix && strpos($field_name, $field_prefix) === 0) {
      return substr($field_name, strlen($field_prefix));
    }

    return $field_name;
  }

  protected function getFieldName(string $attribute_name): string {
    $prefix = $this->getFieldPrefix();
    return strpos($attribute_name, $prefix) === 0 ?
      $attribute_name :
      $prefix . $attribute_name;
  }

  protected function getOriginalFieldData($field_name) {
    if (isset($this->{$field_name})) {
      return $this->{$field_name};
    }

    $attribute_name = $this->getAttributeName($field_name);
    if ($this->attributes->has($attribute_name)) {
      $attribute_value = $this->attributes->getValue($attribute_name);
      return $attribute_value ? json_decode($attribute_value, TRUE) : NULL;
    }

    return NULL;
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
    $this->get($field_name)->setValue($value, $notify);
    return $this;
  }

  public function preSave(EntityStorageInterface $storage) {
    $this->traitPreSave($storage);
    foreach ($this->fields as $field_name => $field) {
      $value = $field->getValue();
      if (isset($this->{$field_name})) {
        $this->{$field_name} = $value;
      }
      else {
        $attribute_name = $this->getAttributeName($field_name);
        $this->attributes->add($attribute_name, json_encode($value));
      }
    }
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
  public function onChange($field_name) {}

  /**
   * {@inheritdoc}
   */
  public function validate() {}

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

}
