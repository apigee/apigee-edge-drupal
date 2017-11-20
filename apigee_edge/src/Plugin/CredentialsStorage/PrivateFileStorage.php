<?php

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
    $base_url = '';
    $organization = '';
    $username = '';
    $password = '';
    $data = file_get_contents(self::FILE_URI);

    if ($data !== FALSE) {
      $stored_credentials = json_decode($data);
      $base_url = $stored_credentials->baseURL;
      $organization = $stored_credentials->organization;
      $username = $stored_credentials->username;
      $password = $stored_credentials->password;
    }

    $credentials = new Credentials();
    $credentials->setBaseURL($base_url);
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
      'baseURL' => $credentials->getBaseURL(),
      'organization' => $credentials->getOrganization(),
      'username' => $credentials->getUsername(),
      'password' => $credentials->getPassword(),
    ]);

    $status = file_save_data($data, self::FILE_URI, FILE_EXISTS_REPLACE);

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
    $data = json_encode([
      'baseURL' => '',
      'organization' => '',
      'username' => '',
      'password' => '',
    ]);
    file_save_data($data, self::FILE_URI, FILE_EXISTS_REPLACE);
  }

}
