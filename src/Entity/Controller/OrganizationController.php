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

use Apigee\Edge\Api\Management\Controller\OrganizationController as EdgeOrganizationController;
use Apigee\Edge\Api\Management\Controller\OrganizationControllerInterface as EdgeOrganizationControllerInterface;
use Apigee\Edge\Entity\EntityInterface;
use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Definition of the Organization controller service.
 *
 * This integrates the Management API's Organization controller from the
 * SDK's with Drupal.
 *
 * We created this to speed up entity controllers that have to use pagination
 * when list entities from Apigee Edge. There is no need to load the same
 * organization profile multiple times in the same page request when all
 * entities are being loaded from Apigee Edge.
 *
 * All paginated entity controllers should use this service.
 *
 * @see \Apigee\Edge\Controller\PaginationHelperTrait::listEntities()
 * @see \Drupal\apigee_edge\Entity\Controller\DeveloperController
 */
final class OrganizationController implements OrganizationControllerInterface {

  /**
   * The internal entity cache.
   *
   * @var \Apigee\Edge\Api\Management\Entity\OrganizationInterface[]
   */
  private $cache = [];

  /**
   * Local cache for the decorated organization controller from the SDK.
   *
   * @var \Apigee\Edge\Api\Management\Controller\OrganizationController|null
   *
   * @see decorated()
   */
  private $instance;

  /**
   * The SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $connector;

  /**
   * OrganizationController constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   */
  public function __construct(SDKConnectorInterface $connector) {
    $this->connector = $connector;
  }

  /**
   * Returns the decorated organization controller from the SDK.
   *
   * @return \Apigee\Edge\Api\Management\Controller\OrganizationControllerInterface
   *   The initialized organization controller.
   */
  private function decorated() : EdgeOrganizationControllerInterface {
    if ($this->instance === NULL) {
      $this->instance = new EdgeOrganizationController($this->connector->getClient());
    }

    return $this->instance;
  }

  /**
   * {@inheritdoc}
   */
  public function create(EntityInterface $entity): void {
    $this->decorated()->create($entity);
    $this->cache[$entity->id()] = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $entity_id): EntityInterface {
    $entity = $this->decorated()->delete($entity_id);
    unset($this->cache[$entity_id]);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entity_id): EntityInterface {
    if (!isset($this->cache[$entity_id])) {
      $this->cache[$entity_id] = $this->decorated()->load($entity_id);
    }
    return $this->cache[$entity_id];
  }

  /**
   * {@inheritdoc}
   */
  public function update(EntityInterface $entity): void {
    $this->decorated()->update($entity);
    $this->cache[$entity->id()] = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(): array {
    $entities = $this->decorated()->getEntities();
    foreach ($entities as $id => $entity) {
      $this->cache[$id] = $entities;
    }
  }

}
