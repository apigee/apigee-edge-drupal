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
  protected $mail;

  /**
   * Whether the Apigee Edge developer should be updated.
   *
   * @var bool
   */
  protected $executeUpdate = FALSE;

  /**
   * DeveloperUpdate constructor.
   *
   * @param string $mail
   *   The email of the developer/user.
   */
  public function __construct(string $mail) {
    parent::__construct();
    $this->mail = $mail;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
    $developer = Developer::load($this->mail);
    /** @var \Drupal\user\UserInterface $account */
    $account = user_load_by_mail($this->mail);

    if ($developer->getFirstName() !== $account->get('first_name')->value) {
      $developer->setFirstName($account->get('first_name')->value);
      $this->executeUpdate = TRUE;
    }

    if ($developer->getLastName() !== $account->get('last_name')->value) {
      $developer->setLastName($account->get('last_name')->value);
      $this->executeUpdate = TRUE;
    }

    $user_fields_to_sync = \Drupal::config('apigee_edge.sync')->get('user_fields_to_sync');
    if (!empty($user_fields_to_sync)) {
      /** @var \Drupal\apigee_edge\FieldStorageFormatManager $format_manager */
      $format_manager = \Drupal::service('plugin.manager.apigee_field_storage_format');
      foreach ($user_fields_to_sync as $field) {
        $field_definition = $account->getFieldDefinition($field);
        if (!isset($field_definition)) {
          $this->recordMessage(t('Skipping @mail developer update, because the field @field does not exist.', [
            '@mail' => $this->mail,
            '@field' => $field_definition->getName(),
          ])->render());
          continue;
        }
        $type = $field_definition->getType();
        $formatter = $format_manager->lookupPluginForFieldType($type);
        if (!isset($formatter)) {
          $this->recordMessage(t('Skipping @mail developer update, because there is no available storage formatter for @field_type.', [
            '@mail' => $this->mail,
            '@field_type' => $type,
          ])->render());
          continue;
        }
        $account_field_value = $formatter->encode($account->get($field)->getValue());
        $encoded = $developer->getAttributeValue(static::getAttributeName($field));
        $developer_attribute_value = isset($encoded) ? $formatter->decode($encoded) : NULL;
        if ($account_field_value !== $developer_attribute_value) {
          $developer->setAttribute(static::getAttributeName($field), $account_field_value);
          $this->executeUpdate = TRUE;
        }
      }
    }

    if ($this->executeUpdate) {
      $developer->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Updating developer (@mail) on Apigee Edge if necessary.', [
      '@mail' => $this->mail,
    ])->render();
  }

}
