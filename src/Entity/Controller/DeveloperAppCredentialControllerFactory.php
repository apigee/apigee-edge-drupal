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

use Drupal\apigee_edge\Entity\Controller\Cache\DeveloperAppCacheFactoryInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The developer app credential controller factory.
 */
final class DeveloperAppCredentialControllerFactory implements DeveloperAppCredentialControllerFactoryInterface {

  /**
   * Internal cache for created instances.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialControllerInterface[]
   */
  private $instances;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $connector;

  /**
   * The developer app cache factory service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\DeveloperAppCacheFactoryInterface
   */
  private $appCacheFactory;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * DeveloperAppCredentialControllerFactory constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\DeveloperAppCacheFactoryInterface $app_cache_factory
   *   The developer app cache factory service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(SDKConnectorInterface $connector, DeveloperAppCacheFactoryInterface $app_cache_factory, EventDispatcherInterface $event_dispatcher) {
    $this->connector = $connector;
    $this->eventDispatcher = $event_dispatcher;
    $this->appCacheFactory = $app_cache_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function developerAppCredentialController(string $owner, string $appName): DeveloperAppCredentialControllerInterface {
    if (!isset($this->instances[$owner][$appName])) {
      $this->instances[$owner][$appName] = new DeveloperAppCredentialController($owner, $appName, $this->connector, $this->appCacheFactory->developerAppCache($owner), $this->eventDispatcher);
    }

    return $this->instances[$owner][$appName];
  }

}
