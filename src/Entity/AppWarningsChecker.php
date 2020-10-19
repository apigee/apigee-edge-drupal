<?php

/**
 * Copyright 2020 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Apigee\Edge\Structure\CredentialProduct;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A service for handling app warnings.
 */
class AppWarningsChecker implements AppWarningsCheckerInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * AppWarningsChecker constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function getWarnings(AppInterface $app): array {
    $warnings = [];
    $warnings['revokedCred'] = FALSE;
    $warnings['revokedOrPendingCredProduct'] = FALSE;
    $warnings['expiredCred'] = FALSE;
    $revoked_credentials = [];
    $args = [
      '@app' => mb_strtolower($app->getEntityType()->getSingularLabel()),
    ];

    foreach ($app->getCredentials() as $credential) {
      // Check for revoked credentials.
      if ($credential->getStatus() === AppCredentialInterface::STATUS_REVOKED) {
        $revoked_credentials[] = $credential;
        continue;
      }

      // Check for expired credentials.
      if (($expired_date = $credential->getExpiresAt()) && $this->time->getRequestTime() - $expired_date->getTimestamp() > 0) {
        $warnings['expiredCred'] = $this->t('At least one of the credentials associated with this @app is expired.', $args);
      }

      // Check status of API products for credential.
      foreach ($credential->getApiProducts() as $cred_product) {
        if ($cred_product->getStatus() == CredentialProduct::STATUS_REVOKED || $cred_product->getStatus() == CredentialProduct::STATUS_PENDING) {
          $args['@api_product'] = $this->entityTypeManager->getDefinition('api_product')
            ->getSingularLabel();
          $args['@status'] = $cred_product->getStatus() == CredentialProduct::STATUS_REVOKED ? $this->t('revoked') : $this->t('pending');
          if (count($app->getCredentials()) === 1) {
            /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $apiProduct */
            $api_product = $this->entityTypeManager->getStorage('api_product')
              ->load($cred_product->getApiproduct());
            $args['%name'] = $api_product->label();
            $warnings['revokedOrPendingCredProduct'] = $this->t('%name @api_product associated with this @app is in @status status.', $args);
          }
          else {
            $warnings['revokedOrPendingCredProduct'] = $this->t('At least one @api_product associated with one of the credentials of this @app is in @status status.', $args);
          }
          break;
        }
      }
    }

    // If all credentials are revoked, show a warning.
    if (count($app->getCredentials()) === count($revoked_credentials)) {
      $warnings['revokedCred'] = $this->t('No valid credentials associated with this @app.', $args);
    }

    return $warnings;
  }

}
