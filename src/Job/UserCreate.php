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

namespace Drupal\apigee_edge\Job;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\FieldableEdgeEntityUtilityTrait;
use Drupal\user\Entity\User;
use Drupal\user\Plugin\Validation\Constraint\UserNameUnique;

/**
 * A job to create a Drupal user from an Apigee Edge developer.
 */
class UserCreate extends EdgeJob {

  use FieldableEdgeEntityUtilityTrait;

  /**
   * The Apigee Edge developer's email.
   *
   * @var string
   */
  protected $email;

  /**
   * {@inheritdoc}
   */
  public function __construct(string $email) {
    parent::__construct();
    $this->email = $email;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
    $developer = Developer::load($this->email);
    /** @var \Drupal\user\UserInterface $user */
    $user = User::create([
      'name' => $developer->getUserName(),
      'mail' => $developer->getEmail(),
      'first_name' => $developer->getFirstName(),
      'last_name' => $developer->getLastName(),
      'status' => $developer->getStatus() === Developer::STATUS_ACTIVE,
      'pass' => user_password(),
    ]);

    /** @var \Drupal\Core\Entity\EntityConstraintViolationListInterface $userNameViolations */
    $userNameViolations = $user->get('name')->validate();
    foreach ($userNameViolations as $violation) {
      // Skip user creation if username is already taken here instead
      // of getting a database exception in a lower layer. Username is not
      // unique in Apigee Edge.
      /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
      if (get_class($violation->getConstraint()) === UserNameUnique::class) {
        $message = 'Skipping creating %email user: %message';
        $context = [
          '%email' => $this->email,
          '%message' => $violation->getMessage(),
        ];
        \Drupal::logger('apigee_edge_sync')->error($message, $context);
        $this->recordMessage(t('Skipping creating %email user: %message', $context)->render());
        return;
      }
    }

    $user_fields_to_sync = \Drupal::config('apigee_edge.sync')->get('user_fields_to_sync');
    if (!empty($user_fields_to_sync)) {
      /** @var \Drupal\apigee_edge\FieldStorageFormatManager $format_manager */
      $format_manager = \Drupal::service('plugin.manager.apigee_field_storage_format');
      foreach ($user_fields_to_sync as $field_name) {
        if (!array_key_exists(static::getAttributeName($field_name), $developer->getAttributes()->values())) {
          $message = "Skipping creating %email user field, because the developer does not have %attribute_name attribute on Apigee Edge.";
          $context = [
            '%email' => $this->email,
            '%attribute_name' => static::getAttributeName($field_name),
          ];
          \Drupal::logger('apigee_edge_sync')->warning($message, $context);
          $this->recordMessage(t("Skipping creating %email user field, because the developer does not have %attribute_name attribute on Apigee Edge.", $context)->render());
          continue;
        }
        $field_definition = $user->getFieldDefinition($field_name);
        // If the field does not exist, then skip it.
        if (!isset($field_definition)) {
          $message = "Skipping creating %email user field, because %field_name field does not exist.";
          $context = [
            '%email' => $this->email,
            '%field_name' => $field_name,
          ];
          \Drupal::logger('apigee_edge_sync')->warning($message, $context);
          $this->recordMessage(t("Skipping creating %email user field, because %field_name field does not exist.", $context)->render());
          continue;
        }
        $field_type = $field_definition->getType();
        $formatter = $format_manager->lookupPluginForFieldType($field_type);
        // If there is no available storage formatter for the field, then skip
        // it.
        if (!isset($formatter)) {
          $message = "Skipping creating %email user's %field_name field, because there is no available storage formatter for %field_type field type.";
          $context = [
            '%email' => $this->email,
            '%field_name' => $field_name,
            '%field_type' => $field_type,
          ];
          \Drupal::logger('apigee_edge_sync')->warning($message, $context);
          $this->recordMessage(t("Skipping creating %email user's %field_name field, because there is no available storage formatter for %field_type field type.", $context)->render());
          continue;
        }

        $rollback = $user->get($field_name)->getValue();
        $developer_attribute_value = $developer->getAttributeValue(static::getAttributeName($field_name));
        $user->set($field_name, $formatter->decode($developer_attribute_value));
        // Do not set the field value if a field constraint fails during
        // validation.
        $field_violations = $user->get($field_name)->validate();
        if ($field_violations->count() > 0) {
          $user->set($field_name, $rollback);
          foreach ($field_violations as $violation) {
            $message = "Skipping creating %email user's %field_name field with %field_value value: %message";
            $context = [
              '%email' => $this->email,
              '%field_name' => $field_name,
              '%field_value' => $developer_attribute_value,
              '%message' => $violation->getMessage(),
            ];
            \Drupal::logger('apigee_edge_sync')->warning($message, $context);
            $this->recordMessage(t("Skipping creating %email user's %field_name field: %message", $context)->render());
          }
        }
      }
    }

    try {
      // If the developer-user synchronization is in progress, then saving
      // the same developer in apigee_edge_user_presave() while creating Drupal
      // user based on a developer should be avoided.
      _apigee_edge_set_sync_in_progress(TRUE);
      $user->save();
    }
    catch (\Exception $exception) {
      $message = 'Skipping creating %email user: %message';
      $context = [
        '%email' => $this->email,
        '%message' => (string) $exception,
      ];
      \Drupal::logger('apigee_edge_sync')->error($message, $context);
      $this->recordMessage(t('Skipping creating %email user: %message', $context)->render());
    }
    finally {
      _apigee_edge_set_sync_in_progress(FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Copying developer (@email) to Drupal from Apigee Edge.', [
      '@email' => $this->email,
    ])->render();
  }

}
