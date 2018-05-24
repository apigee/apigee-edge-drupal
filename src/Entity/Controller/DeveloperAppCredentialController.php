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

namespace Drupal\apigee_edge\Entity\Controller;

use Apigee\Edge\Api\Management\Controller\DeveloperAppCredentialController as EdgeDeveloperAppCredentialController;
use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Apigee\Edge\Entity\EntityInterface;
use Apigee\Edge\Structure\AttributesProperty;
use Drupal\apigee_edge\Entity\AppCredentialStorageAwareTrait;

/**
 * Wrapper around SDK's DeveloperAppCredentialController.
 *
 * It ensures that a user's private credential always gets invalidated when
 * a credential gets updated in Drupal.
 *
 * We have not overrode the load() method, because there is no need to store
 * individual app credentials in the app credential storage. In app credential
 * storage we must store _all_ credentials of an app or none of them.
 */
class DeveloperAppCredentialController extends EdgeDeveloperAppCredentialController {
  use AppCredentialStorageAwareTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function create(string $consumerKey, string $consumerSecret): AppCredentialInterface {
    $credential = parent::create($consumerKey, $consumerSecret);
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $credential;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function generate(
    array $apiProducts,
    AttributesProperty $appAttributes,
    string $callbackUrl,
    array $scopes = [],
    string $keyExpiresIn = '-1'
  ): AppCredentialInterface {
    $credential = parent::generate($apiProducts, $appAttributes, $callbackUrl, $scopes, $keyExpiresIn);
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $credential;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function addProducts(string $consumerKey, array $apiProducts): AppCredentialInterface {
    $credential = parent::addProducts($consumerKey, $apiProducts);
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $credential;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function overrideAttributes(string $consumerKey, AttributesProperty $attributes): AppCredentialInterface {
    $credential = parent::overrideAttributes($consumerKey, $attributes);
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $credential;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function setApiProductStatus(string $consumerKey, string $apiProduct, string $status): void {
    parent::setApiProductStatus($consumerKey, $apiProduct, $status);
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function deleteApiProduct(string $consumerKey, string $apiProduct): EntityInterface {
    $credential = parent::deleteApiProduct($consumerKey, $apiProduct);
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $credential;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function overrideScopes(string $consumerKey, array $scopes): AppCredentialInterface {
    $credential = parent::overrideScopes($consumerKey, $scopes);
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $credential;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function setStatus(string $entityId, string $status): void {
    parent::setStatus($entityId, $status);
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function update(EntityInterface $entity): void {
    parent::update($entity);
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function delete(string $entityId): EntityInterface {
    $entity = parent::delete($entityId);
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $entity;
  }

}
