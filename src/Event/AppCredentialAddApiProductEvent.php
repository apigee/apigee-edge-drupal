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

  /**
   * Event id.
   *
   * @var string
   */
  const EVENT_NAME = 'apigee_edge.app_credential.add_api_product';

  /**
   * Array of recently added API product names.
   *
   * @var string[]
   */
  private $newProducts;

  /**
   * The original scopes before products were added.
   *
   * @var array
   */
  private $originalScopes;

  /**
   * AppCredentialAddApiProductEvent constructor.
   *
   * @param string $app_type
   *   Either company or developer.
   * @param string $owner_id
   *   Company name or developer id (email) depending on the appType.
   * @param string $app_name
   *   Name of the app.
   * @param \Apigee\Edge\Api\Management\Entity\AppCredentialInterface $credential
   *   The app credential that has been created.
   * @param array $new_products
   *   Array of API product names that has just been added to the key.
   * @param array $original_scopes
   *   The original scopes.
   */
  public function __construct(string $app_type, string $owner_id, string $app_name, AppCredentialInterface $credential, array $new_products, array $original_scopes = []) {
    parent::__construct($app_type, $owner_id, $app_name, $credential);
    $this->newProducts = $new_products;
    $this->originalScopes = $original_scopes;
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

  /**
   * Returns the original scopes.
   *
   * @return array
   *   The original scopes.
   */
  public function getOriginalScopes(): array {
    return $this->originalScopes;
  }

}
