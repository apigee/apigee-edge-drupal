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

use Apigee\Edge\Api\Management\Entity\Developer as EdgeDeveloper;
use Drupal\apigee_edge\Job;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the Developer entity class.
 *
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "developer",
 *   label = @Translation("Developer"),
 *   handlers = {
 *     "storage" = "\Drupal\apigee_edge\Entity\Storage\DeveloperStorage",
 *   }
 * )
 */
class Developer extends EdgeDeveloper implements DeveloperInterface {

  use FieldableEdgeEntityUtilityTrait;
  use EdgeEntityBaseTrait {
    id as private traitId;
  }

  /**
   * Developer already exists error code.
   */
  const APIGEE_EDGE_ERROR_CODE_DEVELOPER_ALREADY_EXISTS = 'developer.service.DeveloperAlreadyExists';

  /**
   * Developer does not exists error code.
   */
  const APIGEE_EDGE_ERROR_CODE_DEVELOPER_DOES_NOT_EXISTS = 'developer.service.DeveloperDoesNotExist';

  /**
   * The associated Drupal UID.
   *
   * @var null|int
   */
  protected $drupalUserId;

  /**
   * The original email address of the developer.
   *
   * @var null|string
   */
  protected $originalEmail;

  /**
   * Constructs a Developer object.
   *
   * @param array $values
   *   An array of values to set, keyed by property name. If the entity type
   *   has bundles, the bundle key has to be specified.
   */
  public function __construct(array $values = []) {
    // Callers expect that the status is always either 'active' or 'inactive',
    // never null.
    if (!isset($values['status'])) {
      $values['status'] = static::STATUS_ACTIVE;
    }
    parent::__construct($values);
    $this->entityTypeId = 'developer';
    $this->originalEmail = isset($this->originalEmail) ? $this->originalEmail : $this->email;
  }

  /**
   * Creates developer entity from Drupal user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The Drupal user account.
   * @param \Drupal\apigee_edge\Job $developer_create_job
   *   The DeveloperCreate job object if this function is called from
   *   developer synchronization.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperInterface
   *   The developer entity.
   */
  public static function createFromDrupalUser(UserInterface $user, Job $developer_create_job = NULL): DeveloperInterface {
    $developer_data = [
      'email' => $user->getEmail(),
      'originalEmail' => isset($user->original) ? $user->original->getEmail() : $user->getEmail(),
      'userName' => $user->getAccountName(),
      'firstName' => (string) $user->get('first_name')->value,
      'lastName' => (string) $user->get('last_name')->value,
      'status' => $user->isActive() ? static::STATUS_ACTIVE : static::STATUS_INACTIVE,
    ];

    $developer = !isset($user->original) ? static::create($developer_data) : new static($developer_data);
    $developer->setOwnerId($user->id());

    /** @var \Drupal\apigee_edge\FieldStorageFormatManager $format_manager */
    $format_manager = \Drupal::service('plugin.manager.apigee_field_storage_format');
    foreach (\Drupal::config('apigee_edge.sync')->get('user_fields_to_sync') as $field_name) {
      $field_definition = $user->getFieldDefinition($field_name);
      // If the field does not exist, then skip it.
      if (!isset($field_definition)) {
        $message = "Skipping %mail developer's %attribute_name attribute update, because %field_name field does not exist.";
        $context = [
          '%mail' => $user->getEmail(),
          '%attribute_name' => static::getAttributeName($field_name),
          '%field_name' => $field_name,
          'link' => $user->toLink()->toString(),
        ];
        if (isset($developer_create_job)) {
          \Drupal::logger('apigee_edge')->warning($message, $context);
          $developer_create_job->recordMessage(t("Skipping %mail developer's %attribute_name attribute update, because %field_name field does not exist.", $context)->render());
        }
        else {
          \Drupal::logger('apigee_edge_sync')->warning($message, $context);
        }
        continue;
      }
      $field_type = $field_definition->getType();
      $formatter = $format_manager->lookupPluginForFieldType($field_type);
      // If there is no available storage formatter for the field, then skip it.
      if (!isset($formatter)) {
        $message = "Skipping %mail developer's %attribute_name attribute update, because there is no available storage formatter for %field_type field type.";
        $context = [
          '%mail' => $user->getEmail(),
          '%attribute_name' => static::getAttributeName($field_name),
          '%field_type' => $field_type,
          'link' => $user->toLink()->toString(),
        ];
        if (isset($developer_create_job)) {
          \Drupal::logger('apigee_edge')->warning($message, $context);
          $developer_create_job->recordMessage(t("Skipping %mail developer's %attribute_name attribute update, because there is no available storage formatter for %field_type field type.", $context)->render());
        }
        else {
          \Drupal::logger('apigee_edge_sync')->warning($message, $context);
        }
        continue;
      }

      $developer->setAttribute(static::getAttributeName($field_name), $formatter->encode($user->get($field_name)->getValue()));
    }
    return $developer;
  }

  /**
   * {@inheritdoc}
   */
  public function uuid() {
    return parent::id();
  }

  /**
   * {@inheritdoc}
   */
  public function id(): ?string {
    return $this->originalEmail;
  }

  /**
   * {@inheritdoc}
   */
  public function setEmail(string $email): void {
    parent::setEmail($email);
    if ($this->originalEmail === NULL) {
      $this->originalEmail = $this->email;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setOriginalEmail(string $original_email) {
    $this->originalEmail = $original_email;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->drupalUserId === NULL ? NULL : User::load($this->drupalUserId);
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->drupalUserId = $account->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->drupalUserId;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->drupalUserId = $uid;
  }

}
