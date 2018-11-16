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
   * The decorated controller from the SDK.
   *
   * @var \Apigee\Edge\Api\Management\Controller\OrganizationController
   */
  private $decorated;

  /**
   * OrganizationController constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   */
  public function __construct(SDKConnectorInterface $connector) {
    $this->decorated = new EdgeOrganizationController($connector->getClient());
  }

  /**
   * {@inheritdoc}
   */
  public function create(EntityInterface $entity): void {
    $this->decorated->create($entity);
    $this->cache[$entity->id()] = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $entityId): EntityInterface {
    $entity = $this->decorated->delete($entityId);
    unset($this->cache[$entityId]);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entityId): EntityInterface {
    if (!isset($this->cache[$entityId])) {
      $this->cache[$entityId] = $this->decorated->load($entityId);
    }
    return $this->cache[$entityId];
  }

  /**
   * {@inheritdoc}
   */
  public function update(EntityInterface $entity): void {
    $this->decorated->update($entity);
    $this->cache[$entity->id()] = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(): array {
    $entities = $this->decorated->getEntities();
    foreach ($entities as $id => $entity) {
      $this->cache[$id] = $entities;
    }
  }

}
