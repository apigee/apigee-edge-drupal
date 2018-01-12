<?php

namespace Drupal\apigee_edge\Utility;

use Apigee\Edge\Api\Management\Entity\App;
use Apigee\Edge\Api\Management\Entity\AppCredential;
use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;

/**
 * Provides method to display credential status.
 */
trait CredentialStatusDisplayTrait {

  /**
   * Returns a custom credential status.
   *
   * We merge the status of the app and the credential on the UI as a
   * simplification if the app has only one credential.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $app
   *   The developer app.
   * @param \Apigee\Edge\Api\Management\Entity\AppCredentialInterface $credential
   *   The app credential.
   *
   * @return null|string
   *   Credential status.
   */
  protected function getCredentialStatus(DeveloperAppInterface $app, AppCredentialInterface $credential) {
    if (count($app->getCredentials()) === 1) {
      return $app->getStatus() === App::STATUS_REVOKED || $credential->getStatus() === AppCredential::STATUS_REVOKED ? AppCredential::STATUS_REVOKED : AppCredential::STATUS_APPROVED;
    }
    return $credential->getStatus();
  }

}
