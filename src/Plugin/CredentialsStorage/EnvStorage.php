<?php

namespace Drupal\apigee_edge\Plugin\CredentialsStorage;

use Drupal\apigee_edge\Credentials;
use Drupal\apigee_edge\CredentialsInterface;
use Drupal\apigee_edge\CredentialsStoragePluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Retrieves credentials from the environment variables.
 *
 * @CredentialsStorage(
 *   id = "credentials_storage_env",
 *   name = @Translation("Environment variables"),
 * )
 */
class EnvStorage extends CredentialsStoragePluginBase {

  /**
   * {@inheritdoc}
   */
  public function readonly() : bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRequirements() : string {
    if ($this->loadCredentials()->empty()) {
      return (string) t('Necessary environment variables are not set.');
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function helpText() : ? TranslatableMarkup {
    return t('Environment variables: APIGEE_EDGE_ENDPOINT, APIGEE_EDGE_ORGANIZATION, APIGEE_EDGE_USERNAME, APIGEE_EDGE_PASSWORD');
  }

  /**
   * {@inheritdoc}
   */
  public function loadCredentials() : CredentialsInterface {
    $credentials = new Credentials();
    $credentials->setEndpoint(getenv('APIGEE_EDGE_ENDPOINT'));
    $credentials->setOrganization(getenv('APIGEE_EDGE_ORGANIZATION'));
    $credentials->setUsername(getenv('APIGEE_EDGE_USERNAME'));
    $credentials->setPassword(getenv('APIGEE_EDGE_PASSWORD'));

    return $credentials;
  }

  /**
   * {@inheritdoc}
   */
  public function saveCredentials(CredentialsInterface $credentials) {
  }

  /**
   * {@inheritdoc}
   */
  public function deleteCredentials() {
  }

}
