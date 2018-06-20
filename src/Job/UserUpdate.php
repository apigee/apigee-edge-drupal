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
 * A job to update a Drupal user based on an Apigee Edge developer.
 */
class UserUpdate extends EdgeJob {

  use FieldableEdgeEntityUtilityTrait;

  /**
   * The email of the developer/user.
   *
   * @var string
   */
  protected $mail;

  /**
   * Whether the Drupal user should be updated.
   *
   * @var bool
   */
  protected $executeUpdate = FALSE;

  /**
   * UserUpdate constructor.
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
    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = Developer::load($this->mail);
    /** @var \Drupal\user\Entity\User $account */
    $account = user_load_by_mail($this->mail);

    if ($developer->getFirstName() !== $account->get('first_name')->value) {
      $account->set('first_name', $developer->getFirstName());
      $this->executeUpdate = TRUE;
    }

    if ($developer->getLastName() !== $account->get('last_name')->value) {
      $account->set('last_name', $developer->getLastName());
      $this->executeUpdate = TRUE;
    }

    $user_fields_to_sync = \Drupal::config('apigee_edge.sync')->get('user_fields_to_sync');
    if (!empty($user_fields_to_sync)) {
      /** @var \Drupal\apigee_edge\FieldStorageFormatManager $format_manager */
      $format_manager = \Drupal::service('plugin.manager.apigee_field_storage_format');
      foreach ($user_fields_to_sync as $field) {
        $type = $account->getFieldDefinition($field)->getType();
        $formatter = $format_manager->lookupPluginForFieldType($type);
        $account_field_value = $formatter->encode($account->get($field)->getValue());
        $developer_attribute_value = $developer->getAttributeValue(static::getAttributeName($field));
        if ($developer_attribute_value === NULL) {
          continue;
        }
        $developer_attribute_value = $formatter->decode($developer_attribute_value);
        if ($account_field_value !== $developer_attribute_value) {
          $account->set($field, $developer_attribute_value);
          $this->executeUpdate = TRUE;
        }
      }
    }

    if ($this->executeUpdate) {
      // If the developer-user synchronization is in progress, then saving
      // developers while saving Drupal user should be avoided.
      _apigee_edge_set_sync_in_progress(TRUE);
      $account->save();
      _apigee_edge_set_sync_in_progress(FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Updating user (@mail) in Drupal.', [
      '@mail' => $this->mail,
    ])->render();
  }

}
