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

namespace Drupal\apigee_edge\Plugin\CredentialsStorage;

use Drupal\apigee_edge\Credentials;
use Drupal\apigee_edge\CredentialsInterface;
use Drupal\apigee_edge\CredentialsSaveException;
use Drupal\apigee_edge\CredentialsStoragePluginBase;
use Drupal\Core\Site\Settings;

/**
 * Stores the credentials in a private file.
 *
 * @CredentialsStorage(
 *   id = "credentials_storage_private_file",
 *   name = @Translation("Private file"),
 * )
 */
class PrivateFileStorage extends CredentialsStoragePluginBase {

  private const FILE_URI = 'private://api_credentials.json';

  /**
   * {@inheritdoc}
   */
  public function hasRequirements() : string {
    $private_file_path = Settings::get('file_private_path');
    if (empty($private_file_path)) {
      return 'The private file path must be set for storing credentials in a private file.';
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function loadCredentials() : CredentialsInterface {
    $endpoint = '';
    $organization = '';
    $username = '';
    $password = '';
    $data = file_exists(self::FILE_URI) ? file_get_contents(self::FILE_URI) : FALSE;

    if ($data !== FALSE) {
      $stored_credentials = json_decode($data);
      $endpoint = $stored_credentials->endpoint;
      $organization = $stored_credentials->organization;
      $username = $stored_credentials->username;
      $password = $stored_credentials->password;
    }

    $credentials = new Credentials();
    $credentials->setEndpoint($endpoint);
    $credentials->setOrganization($organization);
    $credentials->setUsername($username);
    $credentials->setPassword($password);

    return $credentials;
  }

  /**
   * {@inheritdoc}
   */
  public function saveCredentials(CredentialsInterface $credentials) {
    $data = json_encode([
      'endpoint' => $credentials->getEndpoint(),
      'organization' => $credentials->getOrganization(),
      'username' => $credentials->getUsername(),
      'password' => $credentials->getPassword(),
    ]);

    $status = \file_unmanaged_save_data($data, self::FILE_URI, FILE_EXISTS_REPLACE);

    if ($status === FALSE) {
      throw new CredentialsSaveException(
        'Unable to save the credentials file. Please check file system settings and the path ' . self::FILE_URI
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCredentials() {
    file_exists(self::FILE_URI) ? file_unmanaged_delete(self::FILE_URI) : FALSE;
  }

}
