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
use Apigee\Edge\Structure\AttributesProperty;
use Drupal\apigee_edge\Entity\AppCredentialStorageAwareTrait;
use Drupal\apigee_edge\Event\AppCredentialAddApiProductEvent;
use Drupal\apigee_edge\Event\AppCredentialCreateEvent;
use Drupal\apigee_edge\Event\AppCredentialDeleteEvent;
use Drupal\apigee_edge\Event\AppCredentialGenerateEvent;

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
   */
  public function create(string $consumer_key, string $consumer_secret): AppCredentialInterface {
    $credential = parent::create($consumer_key, $consumer_secret);
    \Drupal::service('event_dispatcher')->dispatch(AppCredentialCreateEvent::EVENT_NAME, new AppCredentialCreateEvent(AppCredentialCreateEvent::APP_TYPE_DEVELOPER, $this->developerId, $this->appName, $credential));
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $credential;
  }

  /**
   * {@inheritdoc}
   */
  public function generate(
    array $api_products,
    AttributesProperty $app_attributes,
    string $callback_url,
    array $scopes = [],
    string $key_expires_in = '-1'
  ): AppCredentialInterface {
    $credential = parent::generate($api_products, $app_attributes, $callback_url, $scopes, $key_expires_in);
    \Drupal::service('event_dispatcher')->dispatch(AppCredentialGenerateEvent::EVENT_NAME, new AppCredentialGenerateEvent(AppCredentialCreateEvent::APP_TYPE_DEVELOPER, $this->developerId, $this->appName, $credential));
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $credential;
  }

  /**
   * {@inheritdoc}
   */
  public function addProducts(string $consumer_key, array $api_products): AppCredentialInterface {
    $credential = parent::addProducts($consumer_key, $api_products);
    \Drupal::service('event_dispatcher')->dispatch(AppCredentialAddApiProductEvent::EVENT_NAME, new AppCredentialAddApiProductEvent(AppCredentialCreateEvent::APP_TYPE_DEVELOPER, $this->developerId, $this->appName, $credential, $api_products));
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $credential;
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttributes(string $consumer_key, AttributesProperty $attributes): AttributesProperty {
    $attributes = parent::updateAttributes($consumer_key, $attributes);
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $attributes;
  }

  /**
   * {@inheritdoc}
   */
  public function setApiProductStatus(string $consumer_key, string $api_product, string $status): void {
    parent::setApiProductStatus($consumer_key, $api_product, $status);
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteApiProduct(string $consumer_key, string $api_product): AppCredentialInterface {
    $credential = parent::deleteApiProduct($consumer_key, $api_product);
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $credential;
  }

  /**
   * {@inheritdoc}
   */
  public function overrideScopes(string $consumer_key, array $scopes): AppCredentialInterface {
    $credential = parent::overrideScopes($consumer_key, $scopes);
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $credential;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $entity_id, string $status): void {
    parent::setStatus($entity_id, $status);
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $entity_id): AppCredentialInterface {
    $entity = parent::delete($entity_id);
    \Drupal::service('event_dispatcher')->dispatch(AppCredentialDeleteEvent::EVENT_NAME, new AppCredentialDeleteEvent(AppCredentialCreateEvent::APP_TYPE_DEVELOPER, $this->developerId, $this->appName, $entity));
    // We have to clear all, see method's description for explanation.
    $this->clearAppCredentialsFromStorage($this->developerId, $this->appName);
    return $entity;
  }

}
