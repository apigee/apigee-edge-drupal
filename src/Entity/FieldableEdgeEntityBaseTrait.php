<?php

namespace Drupal\apigee_edge\Entity;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

const TYPE_EXCEPTIONS = [
  'apiResources' => 'string[]',
  'apps' => 'string[]',
  'companies' => 'string[]',
  'createdAt' => 'timestamp',
  'description' => 'text_long',
  'environments' => 'string[]',
  'expiresAt' => 'timestamp',
  'issuedAt' => 'timestamp',
  'lastModifiedAt' => 'timestamp',
  'proxies' => 'string[]',
  'scopes' => 'string[]',
  'status' => 'list_string',
];

const FIELD_BLACKLIST = [
  'attributes',
];

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

  /**
   * Parses the properties and its types from the parent class.
   *
   * @return array
   *   The key is the property name, the value is its type, declared in the
   *   docblocks.
   */
  protected static function getProperties(): array {
    $rc = new \ReflectionClass(parent::class);
    $props = [];
    foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
      if (strpos($method->getName(), 'get') !== 0) {
        continue;
      }

      if ($method->getNumberOfParameters() > 0) {
        continue;
      }

      $name = lcfirst(substr($method->getName(), 3));
      if (in_array($name, FIELD_BLACKLIST)) {
        continue;
      }
      $type = array_key_exists($name, TYPE_EXCEPTIONS) ? TYPE_EXCEPTIONS[$name] : 'string';
      $props[$name] = $type;
    }
    return $props;
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
   */
  protected static function getBaseFieldDefinition(string $name, string $type): ?BaseFieldDefinition {
    $label = ucwords(preg_replace('/([a-z])([A-Z])/', '$1 $2', $name));
    if (($is_array = strpos($type, '[]') === strlen($type) - 2)) {
      $type = substr($type, 0, -2);
    }

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
   */
  protected function getFieldPrefix(): string {
    return (string) \Drupal::config('field_ui.settings')->get('field_prefix');
  }

  /**
   * Converts a field name to an attribute name.
   *
   * @param string $field_name
   *
   * @return string
   */
  protected function getAttributeName(string $field_name): string {
    $field_prefix = $this->getFieldPrefix();
    if ($field_prefix && strpos($field_name, $field_prefix) === 0) {
      return substr($field_name, strlen($field_prefix));
    }

    return $field_name;
  }

  /**
   * Converts an attribute name to a field name.
   *
   * @param string $attribute_name
   *
   * @return string
   */
  protected function getFieldName(string $attribute_name): string {
    $prefix = $this->getFieldPrefix();
    return strpos($attribute_name, $prefix) === 0 ?
      $attribute_name :
      $prefix . $attribute_name;
  }

  /**
   * Returns the orignal (stored in edge) data from the field.
   *
   * @param $field_name
   *
   * @return mixed|null
   */
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

    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $definitions */
    $definitions = $this->getFieldDefinitions();

    $this->traitPreSave($storage);

    $rc = new \ReflectionClass($this);
    foreach ($this->fields as $field_name => $field) {
      $setter = 'set' . ucfirst($field_name);
      if (method_exists($this, $setter)) {
        $value = [];

        for ($i = 0, $count = $field->count(); $i < $count; $i++) {
          /** @var \Drupal\Core\Field\FieldItemInterface $item */
          $item = $field->get($i);
          if ($item && ($mainproperty = $item::mainPropertyName())) {
            $value[] = $item->get($mainproperty)->getValue();
          }
        }

        if ($definitions[$field_name]->getCardinality() === 1) {
          $value = reset($value);
        }

        $this->maybeTypeCastFirstParameterValue($rc->getMethod($setter), $value);
        call_user_func([$this, $setter], $value);
      }
      else {
        $value = $field->getValue();
        $attribute_name = $this->getAttributeName($field_name);
        $this->attributes->add($attribute_name, json_encode($value));
      }
    }
  }

  /**
   * Type casts a value to match the setter's type hint.
   *
   * Sometimes there are differences between how a value is stored by the field
   * and the edge sdk connector. A good example is the timestamp: it is stored
   * as an integer in Drupal, and as a string by the SDK.
   *
   * @param \ReflectionMethod $method
   *   Method to get the type hint.
   *
   * @param $value
   *   Value to type cast
   */
  private function maybeTypeCastFirstParameterValue(\ReflectionMethod $method, &$value) {
    static $castable = [
      'boolean', 'bool',
      'integer', 'int',
      'float', 'double',
      'string',
      'array',
    ];

    $parameter = $method->getParameters()[0];
    if (!$parameter) {
      return;
    }

    if (!$parameter->hasType()) {
      return;
    }

    if ($parameter->getType()->allowsNull() && $value === NULL) {
      return;
    }

    if (!in_array($parameter->getType()->getName(), $castable)) {
      return;
    }

    settype($value, $parameter->getType()->getName());
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
