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

use Apigee\Edge\Api\Management\Controller\AppCredentialController as EdgeAppCredentialController;
use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Apigee\Edge\Structure\AttributesProperty;
use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\Event\AppCredentialAddApiProductEvent;
use Drupal\apigee_edge\Event\AppCredentialCreateEvent;
use Drupal\apigee_edge\Event\AppCredentialDeleteEvent;
use Drupal\apigee_edge\Event\AppCredentialGenerateEvent;
use Drupal\apigee_edge\Event\AppCredentialDeleteApiProductEvent;
use Drupal\apigee_edge\SDKConnectorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Base class for developer- and company app credential controller services.
 *
 * This helps to integrate the Management API's app credential controllers
 * from the SDK's with Drupal. It leverages the shared app cache as a
 * non-persistent storage for app credentials - because app credentials must
 * not be saved to Drupal's entity cache, although we should not call Apigee
 * Edge every time to load them.
 * It also triggers app credential events when app credentials are changing.
 *
 * @see \Drupal\apigee_edge\Entity\App::getCredentials()
 */
abstract class AppCredentialControllerBase implements AppCredentialControllerInterface {

  /**
   * Local cache for app credential controller instances.
   *
   * @var \Apigee\Edge\Api\Management\Controller\AppCredentialControllerInterface[]
   *   Instances of app credential controllers.
   */
  protected $instances = [];

  /**
   * Email address or id (UUID) of a developer, or name of a company.
   *
   * @var string
   */
  protected $owner;

  /**
   * Name of the app.
   *
   * @var string
   */
  protected $appName;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $connector;

  /**
   * App owner's dedicated app cache that uses app names as cache ids.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerInterface
   */
  protected $appCacheByOwner;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * AppCredentialControllerBase constructor.
   *
   * @param string $owner
   *   Email address or id (UUID) of a developer, or name of a company.
   * @param string $app_name
   *   Name of an app. (Not an app id, because app credentials endpoints does
   *   not allow to use them.)
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory
   *   The app cache by owner factory service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(string $owner, string $app_name, SDKConnectorInterface $connector, AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory, EventDispatcherInterface $event_dispatcher) {
    $this->owner = $owner;
    $this->appName = $app_name;
    $this->connector = $connector;
    $this->appCacheByOwner = $app_cache_by_owner_factory->getAppCache($owner);
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function addProducts(string $consumerKey, array $apiProducts): AppCredentialInterface {
    $credential = $this->decorated()->addProducts($consumerKey, $apiProducts);
    $this->eventDispatcher->dispatch(AppCredentialAddApiProductEvent::EVENT_NAME, new AppCredentialAddApiProductEvent($this->getAppType(), $this->owner, $this->appName, $credential, $apiProducts));
    // By removing app from cache we force reload the credentials as well.
    $this->appCacheByOwner->removeEntities([$this->appName]);
    return $credential;
  }

  /**
   * {@inheritdoc}
   */
  public function create(string $consumerKey, string $consumerSecret): AppCredentialInterface {
    $credential = $this->decorated()->create($consumerKey, $consumerSecret);
    $this->eventDispatcher->dispatch(AppCredentialCreateEvent::EVENT_NAME, new AppCredentialCreateEvent($this->getAppType(), $this->owner, $this->appName, $credential));
    // By removing app from cache we force reload the credentials as well.
    $this->appCacheByOwner->removeEntities([$this->appName]);
    return $credential;
  }

  /**
   * Returns the decorated app credential controller from the SDK.
   *
   * @return \Apigee\Edge\Api\Management\Controller\AppCredentialController
   *   The initialized app credential controller.
   */
  abstract protected function decorated() : EdgeAppCredentialController;

  /**
   * {@inheritdoc}
   */
  public function delete(string $consumerKey): AppCredentialInterface {
    $credential = $this->decorated()->delete($consumerKey);
    $this->eventDispatcher->dispatch(AppCredentialDeleteEvent::EVENT_NAME, new AppCredentialDeleteEvent($this->getAppType(), $this->owner, $this->appName, $credential));
    // By removing app from cache we force reload the credentials as well.
    $this->appCacheByOwner->removeEntities([$this->appName]);
    return $credential;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteApiProduct(string $consumerKey, string $apiProduct): AppCredentialInterface {
    $credential = $this->decorated()->deleteApiProduct($consumerKey, $apiProduct);
    $this->eventDispatcher->dispatch(AppCredentialDeleteApiProductEvent::EVENT_NAME, new AppCredentialDeleteApiProductEvent($this->getAppType(), $this->owner, $this->appName, $credential, $apiProduct));
    // By removing app from cache we force reload the credentials as well.
    $this->appCacheByOwner->removeEntities([$this->appName]);
    return $credential;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAttribute(string $entityId, string $name): void {
    $this->decorated()->deleteAttribute($entityId, $name);
    // By removing app from cache we force reload the credentials as well.
    $this->appCacheByOwner->removeEntities([$this->appName]);
  }

  /**
   * {@inheritdoc}
   */
  public function generate(array $apiProducts, AttributesProperty $appAttributes, string $callbackUrl, array $scopes = [], string $keyExpiresIn = '-1'): AppCredentialInterface {
    $credential = $this->decorated()->generate($apiProducts, $appAttributes, $callbackUrl, $scopes, $keyExpiresIn);
    $this->eventDispatcher->dispatch(AppCredentialGenerateEvent::EVENT_NAME, new AppCredentialGenerateEvent($this->getAppType(), $this->owner, $this->appName, $credential));
    // By removing app from cache we force reload the credentials as well.
    $this->appCacheByOwner->removeEntities([$this->appName]);
    return $credential;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttribute(string $entityId, string $name): string {
    // TODO Get this from cache if available.
    return $this->decorated()->getAttribute($entityId, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes(string $entityId): AttributesProperty {
    // TODO Get this from cache if available.
    return $this->decorated()->getAttributes($entityId);
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganisationName(): string {
    return $this->decorated()->getOrganisationName();
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $consumerKey): AppCredentialInterface {
    // TODO Get this from app cache if available.
    return $this->decorated()->load($consumerKey);
  }

  /**
   * {@inheritdoc}
   */
  public function overrideScopes(string $consumerKey, array $scopes): AppCredentialInterface {
    $credential = $this->decorated()->overrideScopes($consumerKey, $scopes);
    // By removing app from cache we force reload the credentials as well.
    $this->appCacheByOwner->removeEntities([$this->appName]);
    return $credential;
  }

  /**
   * {@inheritdoc}
   */
  public function setApiProductStatus(string $consumerKey, string $apiProduct, string $status): void {
    $this->decorated()->setApiProductStatus($consumerKey, $apiProduct, $status);
    // By removing app from cache we force reload the credentials as well.
    $this->appCacheByOwner->removeEntities([$this->appName]);
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $consumerKey, string $status): void {
    $this->decorated()->setStatus($consumerKey, $status);
    // By removing app from cache we force reload the credentials as well.
    $this->appCacheByOwner->removeEntities([$this->appName]);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttribute(string $entityId, string $name, string $value): string {
    $value = $this->decorated()->updateAttribute($entityId, $name, $value);
    // By removing app from cache we force reload the credentials as well.
    $this->appCacheByOwner->removeEntities([$this->appName]);
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttributes(string $entityId, AttributesProperty $attributes): AttributesProperty {
    $attributes = $this->decorated()->updateAttributes($entityId, $attributes);
    // By removing app from cache we force reload the credentials as well.
    $this->appCacheByOwner->removeEntities([$this->appName]);
    return $attributes;
  }

  /**
   * Returns either "developer" or "team".
   *
   * @return string
   *   Type of the apps that a credential controller supports.
   *
   * @see \Drupal\apigee_edge\Event\AbstractAppCredentialEvent
   */
  abstract protected function getAppType(): string;

}
