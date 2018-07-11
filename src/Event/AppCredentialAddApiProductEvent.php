<?php

/**
 * Copyright 2018 Google Inc.
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

namespace Drupal\apigee_edge\Event;

use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;

/**
 * Triggered when new API products have been added to an app credential.
 */
class AppCredentialAddApiProductEvent extends AbstractAppCredentialEvent {

  const EVENT_NAME = 'apigee_edge.app_credential.add_api_product';

  /**
   * @var string[]
   */
  private $newProducts;

  /**
   * AppCredentialAddApiProductEvent constructor.
   *
   * @param string $appType
   *   Either company or developer.
   * @param string $ownerId
   *   Company name or developer id (email) depending on the appType.
   * @param string $appName
   *   Name of the app.
   * @param \Apigee\Edge\Api\Management\Entity\AppCredentialInterface $credential
   *   The app credential that has been created.
   * @param array $newProducts
   *   Array of API product names that has just been added to the key.
   */
  public function __construct(string $appType, string $ownerId, string $appName, AppCredentialInterface $credential, array $newProducts) {
    parent::__construct($appType, $ownerId, $appName, $credential);
    $this->newProducts = $newProducts;
  }

  /**
   * Returns new API products added to the credential.
   *
   * @return string[]
   *   Array of API product names.
   */
  public function getNewProducts(): array {
    return $this->newProducts;
  }

}
