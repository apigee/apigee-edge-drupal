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

use Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface;
use Drupal\apigee_edge\Entity\DeveloperAppInterface;

/**
 * A trait to common functions of Apigee Edge credential entity tests.
 */
trait CredsUtilsTrait {

  /**
   * API product to test.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProductInterface
   */
  protected $apiProduct;

  /**
   * Returns the developer app credential controller.
   *
   * @param string $owner
   *   The developer id (UUID), email address or team (company) name.
   * @param string $app_name
   *   The name of an app.
   *
   * @return \Drupal\apigee_edge\Entity\Controller\AppCredentialControllerInterface
   *   The app credential controller.
   */
  protected function getAppCredentialController(string $owner, string $app_name): AppCredentialControllerInterface {
    return \Drupal::service('apigee_edge.controller.developer_app_credential_factory')
      ->developerAppCredentialController($owner, $app_name);
  }

  /**
   * Perform an operation on the given credential (by index) of the app.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $app
   *   The app.
   * @param string $op
   *   The operation to perform (revoke,  delete, generate).
   * @param int $cred_index
   *   The index of the credential (only applies to revoke/delete operations).
   * @param int $expires_in
   *   The milliseconds from now that the cred should expire (only applies for
   *   generate operation). Defaults to "-1" (never).
   */
  protected function operationOnCredential(DeveloperAppInterface $app, $op = 'revoke', $cred_index = 0, $expires_in = -1) {
    $controller = $this->getAppCredentialController($app->getAppOwner(), $app->getName());

    if ($op == 'generate') {
      $controller->generate([$this->apiProduct->id()], $app->getAttributes(), '', [], $expires_in);
      return;
    }

    $key = $app
      ->getCredentials()[$cred_index]
      ->getConsumerKey();

    if ($op == 'revoke') {
      $controller->setStatus($key, AppCredentialControllerInterface::STATUS_REVOKE);
    }
    elseif ($op == 'delete') {
      $controller->delete($key);
    }
  }

}
