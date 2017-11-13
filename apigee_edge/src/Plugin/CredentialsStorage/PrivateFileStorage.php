<?php

namespace Drupal\apigee_edge\Plugin\CredentialsStorage;

use Drupal\apigee_edge\CredentialsStoragePluginBase;

/**
 * Store credentials in a private file.
 *
 * @CredentialsStorage(
 *   id = "credentials_storage_private_file",
 *   name = @Translation("Private file"),
 * )
 */
class PrivateFileStorage extends CredentialsStoragePluginBase {

  /**
   * {@inheritdoc}
   */
  public function loadCredentials() {
    // TODO: Implement loadCredentials() method.
  }

  /**
   * {@inheritdoc}
   */
  public function saveCredentials() {
    // TODO: Implement saveCredentials() method.
  }

}
