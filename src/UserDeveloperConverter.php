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

use Drupal\apigee_edge\Entity\DeveloperInterface;
use Drupal\apigee_edge\Exception\DeveloperToUserConversationInvalidValueException;
use Drupal\apigee_edge\Exception\DeveloperToUserConversionAttributeDoesNotExistException;
use Drupal\apigee_edge\Exception\UserDeveloperConversionNoStorageFormatterFoundException;
use Drupal\apigee_edge\Exception\UserDeveloperConversionUserFieldDoesNotExistException;
use Drupal\apigee_edge\Plugin\FieldStorageFormatManagerInterface;
use Drupal\apigee_edge\Plugin\Validation\Constraint\DeveloperEmailUniqueValidator;
use Drupal\apigee_edge\Structure\DeveloperToUserConversionResult;
use Drupal\apigee_edge\Structure\UserToDeveloperConversionResult;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;

/**
 * Default user-developer converter service implementation.
 */
class UserDeveloperConverter implements UserDeveloperConverterInterface {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Field storage formatter service.
   *
   * @var \Drupal\apigee_edge\Plugin\FieldStorageFormatManagerInterface
   */
  protected $fieldStorageFormatManager;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Field-attribute converter service.
   *
   * @var \Drupal\apigee_edge\FieldAttributeConverterInterface
   */
  protected $fieldAttributeConverter;

  /**
   * UserToDeveloper constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\apigee_edge\Plugin\FieldStorageFormatManagerInterface $field_storage_manager
   *   Field storage manager service.
   * @param \Drupal\apigee_edge\FieldAttributeConverterInterface $field_attribute_converter
   *   Field name to attribute name converter service.
   */
  public function __construct(ConfigFactory $config_factory, EntityTypeManagerInterface $entity_type_manager, FieldStorageFormatManagerInterface $field_storage_manager, FieldAttributeConverterInterface $field_attribute_converter) {
    $this->configFactory = $config_factory;
    $this->fieldStorageFormatManager = $field_storage_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldAttributeConverter = $field_attribute_converter;
  }

  /**
   * {@inheritdoc}
   */
  public function convertUser(UserInterface $user): UserToDeveloperConversionResult {
    $problems = [];
    $successful_changes = 0;
    $email = isset($user->original) ? $user->original->getEmail() : $user->getEmail();
    $developer = $this->entityTypeManager->getStorage('developer')->load($email);
    if (!$developer) {
      /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
      $developer = $this->entityTypeManager->getStorage('developer')->create([]);
      $developer->setOwnerId($user->id());
    }

    // Synchronise values of base fields.
    foreach (static::DEVELOPER_PROP_USER_BASE_FIELD_MAP as $developer_prop => $base_field) {
      $setter = 'set' . ucfirst($developer_prop);
      $getter = 'get' . ucfirst($developer_prop);

      // Default value for firstname lastname if null.
      if ($user->get($base_field)->value === NULL && ($base_field === "first_name" || $base_field === "last_name")) {
        $base_field_value = $developer->{$getter}() !== NULL ? $developer->{$getter}() : ucfirst($developer_prop);
        $user->set($base_field, $base_field_value);
      }

      if ($user->get($base_field)->value !== $developer->{$getter}()) {
        $developer->{$setter}($user->get($base_field)->value);
        $successful_changes++;
      }
    }

    // Synchronise statuses.
    if ($user->isActive() && $developer->getStatus() === DeveloperInterface::STATUS_INACTIVE) {
      $developer->setStatus(DeveloperInterface::STATUS_ACTIVE);
      $successful_changes++;
    }
    elseif (!$user->isActive() && $developer->getStatus() === DeveloperInterface::STATUS_ACTIVE) {
      $developer->setStatus(DeveloperInterface::STATUS_INACTIVE);
      $successful_changes++;
    }

    foreach ($this->configFactory->get('apigee_edge.sync')->get('user_fields_to_sync') as $field_name) {
      $field_definition = $user->getFieldDefinition($field_name);
      // If the field does not exist, then skip it.
      $attribute_name = $this->fieldAttributeConverter->getAttributeName($field_name);
      if (!isset($field_definition)) {
        $problems[] = new UserDeveloperConversionUserFieldDoesNotExistException($field_name);
        continue;
      }
      $field_type = $field_definition->getType();
      $formatter = $this->fieldStorageFormatManager->lookupPluginForFieldType($field_type);
      // If there is no available storage formatter for the field, then skip it.
      if (!isset($formatter)) {
        $problems[] = new UserDeveloperConversionNoStorageFormatterFoundException($field_definition);
        continue;
      }

      $formatted_field_value = $formatter->encode($user->get($field_name)->getValue());
      // Do not apply unnecessary changes.
      if ($developer->isNew() || ($developer->getAttributeValue($attribute_name) !== $formatted_field_value)) {
        // Do not leave empty attributes on developers because Apigee Edge
        // Management UI does not like them. (It does not allow to save
        // entities with empty attribute values.)
        if ($formatted_field_value === '') {
          $developer->deleteAttribute($attribute_name);
        }
        else {
          $developer->setAttribute($attribute_name, $formatted_field_value);
        }
        $successful_changes++;
      }
    }

    return new UserToDeveloperConversionResult($developer, $successful_changes, $problems);
  }

  /**
   * {@inheritdoc}
   */
  public function convertDeveloper(DeveloperInterface $developer) : DeveloperToUserConversionResult {
    $successful_changes = 0;
    $problems = [];
    $user_storage = $this->entityTypeManager->getStorage('user');
    // id() always contains the original, unchanged email address of a
    // developer.
    $users = $user_storage->loadByProperties(['mail' => $developer->id()]);
    $user = $users ? reset($users) : FALSE;

    /** @var \Drupal\user\UserInterface $user */
    if (!$user) {
      // Initialize new user object with minimum data.
      $user = $user_storage->create([
        'pass' => user_password(),
      ]);
      // Suppress invalid email validation errors.
      DeveloperEmailUniqueValidator::whitelist($developer->id());
    }

    // Validate base field values that we care about.
    foreach (static::DEVELOPER_PROP_USER_BASE_FIELD_MAP as $developer_prop => $base_field) {
      $getter = 'get' . ucfirst($developer_prop);
      // Do not change the value of the base field unless it has not changed.
      if ($developer->{$getter}() !== $user->get($base_field)->value) {
        $user->set($base_field, $developer->{$getter}());
        $violations = $user->get($base_field)->validate();
        if ($violations->count() > 0) {
          foreach ($violations as $violation) {
            $problems[] = new DeveloperToUserConversationInvalidValueException($developer_prop, $base_field, $violation, $developer);
          }
        }
        else {
          $successful_changes++;
        }
      }
    }

    // Synchronise statuses.
    if ($developer->getStatus() === DeveloperInterface::STATUS_INACTIVE && $user->isActive()) {
      $user->block();
      $successful_changes++;
    }
    elseif ($developer->getStatus() === DeveloperInterface::STATUS_ACTIVE && $user->isBlocked()) {
      $user->activate();
      $successful_changes++;
    }

    foreach ($this->configFactory->get('apigee_edge.sync')->get('user_fields_to_sync') as $field_name) {
      $attribute_name = $this->fieldAttributeConverter->getAttributeName($field_name);
      if (!$developer->getAttributes()->has($attribute_name)) {
        $problems[] = new DeveloperToUserConversionAttributeDoesNotExistException($attribute_name, $developer);
        continue;
      }
      $field_definition = $user->getFieldDefinition($field_name);
      // If the field does not exist, then skip it.
      if (!isset($field_definition)) {
        $problems[] = new UserDeveloperConversionUserFieldDoesNotExistException($field_name);
        continue;
      }
      $field_type = $field_definition->getType();
      $formatter = $this->fieldStorageFormatManager->lookupPluginForFieldType($field_type);
      // If there is no available storage formatter for the field then skip
      // it.
      if (!isset($formatter)) {
        $problems[] = new UserDeveloperConversionNoStorageFormatterFoundException($field_definition);
        continue;
      }

      $developer_attribute_value = $developer->getAttributeValue($attribute_name);
      $user->set($field_name, $formatter->decode($developer_attribute_value));
      $violations = $user->get($field_name)->validate();
      if ($violations->count() > 0) {
        // Clear invalid field value.
        $user->set($field_name, NULL);
        foreach ($violations as $violation) {
          $problems[] = new DeveloperToUserConversationInvalidValueException($attribute_name, $field_name, $violation, $developer);
        }
      }
      else {
        $successful_changes++;
      }
    }

    return new DeveloperToUserConversionResult($user, $successful_changes, $problems);
  }

}
