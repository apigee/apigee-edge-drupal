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

namespace Drupal\apigee_edge_test\Plugin\KeyProvider;

use Drupal\apigee_edge\Plugin\KeyProvider\EnvironmentVariablesKeyProvider;
use Drupal\key\KeyInterface;

/**
 * Overrides the `apigee_edge_environment_variables` plugin.
 */
class TestEnvironmentVariablesKeyProvider extends EnvironmentVariablesKeyProvider {

  const KEY_VALUE_STATE_ID = 'apigee_edge_test_key_value';

  /**
   * {@inheritdoc}
   */
  public function setKeyValue(KeyInterface $key, $key_value) {
    // Store the value in state for functional callbacks.
    \Drupal::state()->set(static::KEY_VALUE_STATE_ID, $key_value);

    return parent::setKeyValue($key, $key_value);
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValue(KeyInterface $key) {
    $key_value = parent::getKeyValue($key);

    // If the key_value is empty during a request callback get credentials from
    // state.
    $key_value = (!empty($key_value) && $key_value !== '{}') ? $key_value
      : \Drupal::state()->get(static::KEY_VALUE_STATE_ID);

    return $key_value;
  }

}
