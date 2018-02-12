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
