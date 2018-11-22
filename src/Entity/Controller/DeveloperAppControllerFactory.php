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
use Egulias\EmailValidator\EmailValidatorInterface;

/**
 * Developer app controller factory.
 */
final class DeveloperAppControllerFactory implements DeveloperAppControllerFactoryInterface {

  /**
   * Internal cache for created instances.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppControllerInterface[]
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
   * The developer app cache factory service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\DeveloperAppCacheFactoryInterface
   */
  private $appCacheFactory;

  /**
   * The email validator service.
   *
   * @var \Egulias\EmailValidator\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * DeveloperAppControllerFactory constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\DeveloperAppCacheFactoryInterface $app_cache_factory
   *   The developer app cache factory service.
   * @param \Egulias\EmailValidator\EmailValidatorInterface $email_validator
   *   The email validator service.
   */
  public function __construct(SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller, DeveloperAppCacheFactoryInterface $app_cache_factory, EmailValidatorInterface $email_validator) {
    $this->emailValidator = $email_validator;
    $this->connector = $connector;
    $this->appCacheFactory = $app_cache_factory;
    $this->orgController = $org_controller;
  }

  /**
   * {@inheritdoc}
   */
  public function developerAppController(string $developer): DeveloperAppControllerInterface {
    if (!isset($this->instances[$developer])) {
      $this->instances[$developer] = new DeveloperAppController($developer, $this->connector, $this->orgController, $this->appCacheFactory->developerAppCache($developer), $this->emailValidator);
    }

    return $this->instances[$developer];
  }

}
