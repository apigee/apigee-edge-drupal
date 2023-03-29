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

use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface;
use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Base class for all app controller services in Drupal.
 *
 * Ex. app, developer app, company app.
 */
abstract class AppControllerBase implements EntityCacheAwareControllerInterface {

  /**
   * The organization controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface
   */
  protected $organizationController;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  protected $connector;

  /**
   * The app cache.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface
   */
  protected $appCache;

  /**
   * AppControllerBase constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache
   *   The app cache.
   */
  public function __construct(SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller, AppCacheInterface $app_cache) {
    $this->connector = $connector;
    $this->organizationController = $org_controller;
    $this->appCache = $app_cache;
  }

  /**
   * {@inheritdoc}
   */
  public function entityCache(): EntityCacheInterface {
    // Developer apps should always expose the app cache as their entity cache
    // because this stores the actual app objects.
    return $this->appCache;
  }

}
