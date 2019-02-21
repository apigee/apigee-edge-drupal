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

namespace Drupal\apigee_edge_teams\Entity\Controller;

use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The team app credential controller factory.
 */
final class TeamAppCredentialControllerFactory implements TeamAppCredentialControllerFactoryInterface {

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
   * The app cache by owner factory service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface
   */
  private $appCacheByOwnerFactory;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * TeamAppCredentialControllerFactory constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory
   *   The app cache by owner factory service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(SDKConnectorInterface $connector, AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory, EventDispatcherInterface $event_dispatcher) {
    $this->connector = $connector;
    $this->eventDispatcher = $event_dispatcher;
    $this->appCacheByOwnerFactory = $app_cache_by_owner_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function teamAppCredentialController(string $owner, string $app_name): TeamAppCredentialControllerInterface {
    if (!isset($this->instances[$owner][$app_name])) {
      $this->instances[$owner][$app_name] = new TeamAppCredentialController($owner, $app_name, $this->connector, $this->appCacheByOwnerFactory, $this->eventDispatcher);
    }

    return $this->instances[$owner][$app_name];
  }

}
