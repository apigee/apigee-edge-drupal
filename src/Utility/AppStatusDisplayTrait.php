<?php

namespace Drupal\apigee_edge\Utility;

use Apigee\Edge\Api\Management\Entity\App;
use Apigee\Edge\Api\Management\Entity\AppCredential;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;

trait AppStatusDisplayTrait {

  /**
   * Returns a custom app status based on the number of credentials of an app.
   *
   * We merge the status of the app and its first credential on the UI as a
   * simplification if an app has only one credential.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $app
   *   Developer app.
   *
   * @return null|string
   *   App status.
   */
  protected function getAppStatus(DeveloperAppInterface $app) {
    if (count($app->getCredentials()) === 1) {
      return $app->getStatus() === App::STATUS_REVOKED || $app->getCredentials()[0]->getStatus() === AppCredential::STATUS_REVOKED ? App::STATUS_REVOKED : App::STATUS_APPROVED;
    }
    return $app->getStatus();
  }

}
