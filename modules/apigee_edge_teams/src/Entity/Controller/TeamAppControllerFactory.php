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

use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface;
use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Company app controller factory.
 */
final class TeamAppControllerFactory implements TeamAppControllerFactoryInterface {

  /**
   * Internal cache for created instances.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Controller\TeamAppControllerInterface[]
   */
  private $instances = [];

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $connector;

  /**
   * The organization controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface
   */
  private $orgController;

  /**
   * The app cache that stores apps by their ids (UUIDs).
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface
   */
  private $appCache;

  /**
   * The app cache by owner factory service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface
   */
  private $appCacheByOwnerFactory;

  /**
   * The app name cache by owner factory service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface
   */
  private $appNameCacheByOwnerFactory;

  /**
   * DeveloperAppControllerFactory constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache
   *   The app cache that stores apps by their ids (UUIDs).
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface $app_by_owner_app_cache_factory
   *   The app cache by owner factory service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface $app_by_owner_app_id_cache_factory
   *   The app name cache by owner factory service.
   */
  public function __construct(SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller, AppCacheInterface $app_cache, AppCacheByOwnerFactoryInterface $app_by_owner_app_cache_factory, AppNameCacheByOwnerFactoryInterface $app_by_owner_app_id_cache_factory) {
    $this->connector = $connector;
    $this->orgController = $org_controller;
    $this->appCache = $app_cache;
    $this->appCacheByOwnerFactory = $app_by_owner_app_cache_factory;
    $this->appNameCacheByOwnerFactory = $app_by_owner_app_id_cache_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function teamAppController(string $team): TeamAppControllerInterface {
    if (!isset($this->instances[$team])) {
      $this->instances[$team] = new TeamAppController($team, $this->connector, $this->orgController, $this->appCache, $this->appCacheByOwnerFactory, $this->appNameCacheByOwnerFactory);
    }

    return $this->instances[$team];
  }

}
