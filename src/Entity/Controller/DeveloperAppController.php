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

use Apigee\Edge\Api\Management\Controller\AppByOwnerControllerInterface as EdgeAppByOwnerControllerInterface;
use Apigee\Edge\Api\Management\Controller\DeveloperAppController as EdgeDeveloperAppController;
use Apigee\Edge\Entity\EntityInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Egulias\EmailValidator\EmailValidatorInterface;

/**
 * Definition of the developer app controller service.
 *
 * This integrates the Management API's developer app controller from the
 * SDK's with Drupal. It uses a shared (not internal) app cache to reduce the
 * number of API calls that we send to Apigee Edge.
 */
final class DeveloperAppController extends AppByOwnerController implements DeveloperAppControllerInterface {

  /**
   * The email validator service.
   *
   * @var \Egulias\EmailValidator\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * The app cache by owner factory service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface
   */
  private $appCacheByOwnerFactory;

  /**
   * DeveloperAppController constructor.
   *
   * @param string $owner
   *   A developer's email address, uuid or a company's company name.
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache
   *   The app cache that stores apps by their ids (UUIDs).
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory
   *   The app cache by owner factory service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner_factory
   *   The app name cache by owner factory service.
   * @param \Egulias\EmailValidator\EmailValidatorInterface $email_validator
   *   The email validator service.
   */
  public function __construct(string $owner, SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller, AppCacheInterface $app_cache, AppCacheByOwnerFactoryInterface $app_cache_by_owner_factory, AppNameCacheByOwnerFactoryInterface $app_name_cache_by_owner_factory, EmailValidatorInterface $email_validator) {
    parent::__construct($owner, $connector, $org_controller, $app_cache, $app_cache_by_owner_factory, $app_name_cache_by_owner_factory);
    $this->emailValidator = $email_validator;
    $this->appCacheByOwnerFactory = $app_cache_by_owner_factory;
  }

  /**
   * {@inheritdoc}
   */
  protected function decorated(): EdgeAppByOwnerControllerInterface {
    if (!isset($this->instances[$this->owner])) {
      $this->instances[$this->owner] = new EdgeDeveloperAppController($this->connector->getOrganization(), $this->owner, $this->connector->getClient(), NULL, $this->organizationController);
    }
    return $this->instances[$this->owner];
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entityId): EntityInterface {
    if ($entity = $this->appCacheByOwner->getEntity($entityId)) {
      return $entity;
    }
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface $entity */
    $entity = parent::load($entityId);
    $this->saveAppsToAppByOwnerCacheByDeveloperId([$entity]);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(): array {
    if ($this->appCacheByOwner->isAllEntitiesInCache()) {
      return $this->appCacheByOwner->getEntities();
    }
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface[] $entities */
    $entities = parent::getEntities();
    $this->saveAppsToAppByOwnerCacheByDeveloperId($entities);
    return $entities;
  }

  /**
   * Saves apps to the app by owner cache that belongs to developer's email.
   *
   * If apps loaded by using developer's email address (instead of UUID)
   * then this method saves the loaded apps to the cache which belongs to the
   * same developer by its developer id (UUID).
   * (Parent class automatically saves them by using the email address as
   *  owner.)
   * Thanks for this we may not need to call Apigee Edge if load this
   * developer's app again in the same page request by using developer id
   * (UUID) as $this->owner.
   *
   * @param \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface[] $entities
   *   Array of developer apps.
   */
  private function saveAppsToAppByOwnerCacheByDeveloperId(array $entities): void {
    if (!empty($entities) && $this->emailValidator->isValid($this->owner)) {
      $entity = reset($entities);
      $app_by_owner_cache_by_developer_id = $this->appCacheByOwnerFactory->getAppCache($entity->getDeveloperId());
      $app_by_owner_cache_by_developer_id->saveEntities([$entity]);
    }
  }

}
