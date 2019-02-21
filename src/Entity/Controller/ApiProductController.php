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

use Apigee\Edge\Api\Management\Controller\ApiProductController as EdgeApiProductController;
use Apigee\Edge\Api\Management\Controller\ApiProductControllerInterface as EdgeApiProductControllerInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface;
use Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface;
use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Definition of the API product controller service.
 *
 * This integrates the Management API's API product controller from the
 * SDK's with Drupal.
 */
final class ApiProductController implements ApiProductControllerInterface, EntityCacheAwareControllerInterface {

  use CachedEntityCrudOperationsControllerTrait;
  use CachedPaginatedEntityIdListingControllerTrait;
  use CachedPaginatedEntityListingControllerTrait;
  use CachedPaginatedControllerHelperTrait;
  use CachedAttributesAwareEntityControllerTrait;

  /**
   * Local cache for the decorated API product controller from the SDK.
   *
   * @var \Apigee\Edge\Api\Management\Controller\ApiProductController|null
   *
   * @see decorated()
   */
  private $instance;

  /**
   * The SDK connector.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $connector;

  /**
   * The entity cache.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface
   */
  private $entityCache;

  /**
   * The entity id cache.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface
   */
  private $entityIdCache;

  /**
   * The organization controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface
   */
  private $orgController;

  /**
   * ApiProductController constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface $entity_cache
   *   The entity cache used by this controller.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface $entity_id_cache
   *   The entity id cache used by this controller.
   */
  public function __construct(SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller, EntityCacheInterface $entity_cache, EntityIdCacheInterface $entity_id_cache) {
    $this->connector = $connector;
    $this->orgController = $org_controller;
    $this->entityCache = $entity_cache;
    $this->entityIdCache = $entity_id_cache;
  }

  /**
   * Returns the decorated API product controller from the SDK.
   *
   * @return \Apigee\Edge\Api\Management\Controller\ApiProductControllerInterface
   *   The initialized API product controller.
   */
  protected function decorated(): EdgeApiProductControllerInterface {
    if ($this->instance === NULL) {
      $this->instance = new EdgeApiProductController($this->connector->getOrganization(), $this->connector->getClient(), NULL, $this->orgController);
    }

    return $this->instance;
  }

  /**
   * {@inheritdoc}
   */
  public function searchByAttribute(string $attribute_name, string $attribute_value): array {
    return $this->decorated()->searchByAttribute($attribute_name, $attribute_value);
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
  public function entityCache(): EntityCacheInterface {
    return $this->entityCache;
  }

  /**
   * {@inheritdoc}
   */
  protected function entityIdCache(): EntityIdCacheInterface {
    return $this->entityIdCache;
  }

}
