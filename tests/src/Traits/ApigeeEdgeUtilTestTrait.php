<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\Tests\apigee_edge\Traits;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\key\Entity\Key;

/**
 * Provides common functionality for the Apigee Edge test classes.
 */
trait ApigeeEdgeUtilTestTrait {

  /**
   * Creates a test key from environment variables, using config key storage.
   *
   * Using config storage , as opposed to environment vars, has the advantage
   * of the key values persisting in subsequent page requests.
   */
  protected function createTestKey(): void {
    $environment_variables = [];
    $definition = \Drupal::service('plugin.manager.key.key_type')->getDefinition('apigee_auth');
    foreach ($definition['multivalue']['fields'] as $id => $field) {
      $env_var_name = 'APIGEE_EDGE_' . mb_strtoupper($id);
      if (getenv($env_var_name)) {
        $environment_variables[$id] = getenv($env_var_name);
      }
    }

    $key = Key::create([
      'id' => 'test',
      'label' => 'test',
      'key_type' => 'apigee_auth',
      'key_provider' => 'config',
      'key_input' => 'none',
      'key_provider_settings' => ['key_value' => json_encode($environment_variables)],
    ]);
    try {
      $key->save();
    }
    catch (EntityStorageException $exception) {
      $this->fail('Could not create key for testing.');
    }
  }

  /**
   * Restores the active key.
   */
  protected function restoreKey() {
    $test_key_id = 'test';
    $this->config('apigee_edge.auth')
      ->set('active_key', $test_key_id)
      ->save();
  }

  /**
   * Removes the active key for testing with unset API credentials.
   */
  protected function invalidateKey() {
    $this->config('apigee_edge.auth')
      ->set('active_key', '')
      ->save();
  }

  /**
   * Set active authentication keys in config.
   *
   * @param string $active_key
   *   The active authentication key.
   */
  protected function setKey(string $active_key) {
    $this->config('apigee_edge.auth')
      ->set('active_key', $active_key)
      ->save();
  }

}
