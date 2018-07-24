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

/**
 * A job to update an Apigee Edge developer based on a Drupal user.
 */
class DeveloperUpdate extends EdgeJob {

  use FieldableEdgeEntityUtilityTrait;

  /**
   * The email of the developer/user.
   *
   * @var string
   */
  protected $email;

  /**
   * Whether the Apigee Edge developer should be updated.
   *
   * @var bool
   */
  protected $executeUpdate = FALSE;

  /**
   * DeveloperUpdate constructor.
   *
   * @param string $email
   *   The email of the developer/user.
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
    $user = user_load_by_mail($this->email);

    if ($developer->getUserName() !== $user->get('name')->value) {
      $developer->setUserName($user->get('name')->value);
      $this->executeUpdate = TRUE;
    }

    if ($developer->getFirstName() !== $user->get('first_name')->value) {
      $developer->setFirstName($user->get('first_name')->value);
      $this->executeUpdate = TRUE;
    }

    if ($developer->getLastName() !== $user->get('last_name')->value) {
      $developer->setLastName($user->get('last_name')->value);
      $this->executeUpdate = TRUE;
    }

    $user_fields_to_sync = \Drupal::config('apigee_edge.sync')->get('user_fields_to_sync');
    if (!empty($user_fields_to_sync)) {
      /** @var \Drupal\apigee_edge\FieldStorageFormatManager $format_manager */
      $format_manager = \Drupal::service('plugin.manager.apigee_field_storage_format');
      foreach ($user_fields_to_sync as $field_name) {
        $field_definition = $user->getFieldDefinition($field_name);
        // If the field does not exist, then skip it.
        if (!isset($field_definition)) {
          $message = "Skipping %email developer's %attribute_name attribute update, because %field_name field does not exist.";
          $context = [
            '%email' => $this->email,
            '%attribute_name' => static::getAttributeName($field_name),
            '%field_name' => $field_definition->getName(),
            'link' => $user->toLink(t('View user'))->toString(),
          ];
          \Drupal::logger('apigee_edge_sync')->warning($message, $context);
          $this->recordMessage(t("Skipping %email developer's %attribute_name attribute update, because %field_name field does not exist.", $context)->render());
          continue;
        }
        $field_type = $field_definition->getType();
        $formatter = $format_manager->lookupPluginForFieldType($field_type);
        // If there is no available storage formatter for the field, then skip
        // it.
        if (!isset($formatter)) {
          $message = "Skipping %email developer's %attribute_name attribute update, because there is no available storage formatter for %field_type field type.";
          $context = [
            '%email' => $this->email,
            '%attribute_name' => static::getAttributeName($field_name),
            '%field_type' => $field_type,
            'link' => $user->toLink(t('View user'))->toString(),
          ];
          \Drupal::logger('apigee_edge_sync')->warning($message, $context);
          $this->recordMessage(t("Skipping %email developer's %attribute_name attribute update, because there is no available storage formatter for %field_type field type.", $context)->render());
          continue;
        }

        $user_field_value = $formatter->encode($user->get($field_name)->getValue());
        $developer_attribute_value = $developer->getAttributeValue(static::getAttributeName($field_name));
        if ($user_field_value !== $developer_attribute_value) {
          $developer->setAttribute(static::getAttributeName($field_name), $user_field_value);
          $this->executeUpdate = TRUE;
        }
      }
    }

    if ($this->executeUpdate) {
      try {
        $developer->save();
      }
      catch (\Exception $exception) {
        $message = 'Skipping refreshing %email developer: %message';
        $context = [
          '%email' => $this->email,
          '%message' => (string) $exception,
          'link' => $user->toLink(t('View user'))->toString(),
        ];
        \Drupal::logger('apigee_edge_sync')->error($message, $context);
        $this->recordMessage(t('Skipping refreshing %email developer: %message', $context)->render());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Refreshing developer (@email) on Apigee Edge.', [
      '@email' => $this->email,
    ])->render();
  }

}
