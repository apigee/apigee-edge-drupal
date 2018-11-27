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

namespace Drupal\Tests\apigee_edge\Traits;

use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\key\Entity\Key;

/**
 * A trait to build test keys.
 */
trait TestKeyBuilderTrait {

  /**
   * A test key.
   *
   * @var \Drupal\key\KeyInterface
   */
  protected $test_key;

  /**
   * Creates a test key and saves it.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function generateTestKey() {
    // The directory has to be passed by reference.
    $key_folder = 'public://.apigee_edge';
    file_prepare_directory($key_folder, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

    // Create a new key name.
    $new_key_id = 'apigee_edge_connection_default';
    $key_file_path = 'public://.apigee_edge/apigee_auth_apigee_edge.json';

    // Save an empty object to the file.
    file_put_contents($key_file_path, '{"auth_type": "basic"}');

    // Create a new key.
    $this->test_key = Key::create([
      'id' => $new_key_id,
      'label' => $this->randomMachineName(),
      'description' => $this->getRandomGenerator()->paragraphs(1),
      'key_type' => 'apigee_auth',
      'key_input' => 'apigee_auth_input',
      'key_provider' => 'file',
      'key_provider_settings' => [
        'file_location' => $key_file_path,
      ],
    ]);

    $this->test_key->save();
  }

  /**
   * Populates key value data.
   */
  public function populateTestKeyValues() {
    $input_settings = [
      'auth_type' => EdgeKeyTypeInterface::EDGE_AUTH_TYPE_OAUTH,
      'organization' => strtolower($this->randomMachineName()),
      'username' => strtolower($this->randomMachineName()),
      'password' => $this->randomString(16),
      'endpoint' => '',
      'authorization_server' => '',
      'client_id' => strtolower($this->randomMachineName()),
      'client_secret' => strtolower($this->randomMachineName()),
    ];

    /** @var \Drupal\key\Plugin\KeyProvider\FileKeyProvider $provider */
    $provider = $this->test_key->getKeyProvider();
    $file_path = $provider->getConfiguration()['file_location'];

    // Save the data to the key.
    $file_content = Json::encode(array_filter($input_settings));
    file_put_contents($file_path, $file_content);

    // Reset static cache.
    static::assertSame($file_content, $this->test_key->getKeyValue(TRUE));
  }

}
