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

use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Apigee\Edge\Controller\NonPaginatedEntityListingControllerInterface;
use Apigee\Edge\Controller\PaginatedEntityListingControllerInterface;
use Apigee\Edge\Entity\EntityInterface;
use Drupal\apigee_edge\Exception\InvalidArgumentException;

/**
 * Management API specific default entity controller implementation.
 */
final class ManagementApiEdgeEntityControllerProxy implements EdgeEntityControllerInterface {

  /**
   * The decorated controller from the SDK.
   *
   * @var \Apigee\Edge\Controller\EntityCrudOperationsControllerInterface|\Apigee\Edge\Controller\NonPaginatedEntityListingControllerInterface|\Apigee\Edge\Controller\PaginatedEntityListingControllerInterface
   */
  private $controller;

  /**
   * ManagementApiEntityControllerBase constructor.
   *
   * @param object $controller
   *   A controller from the SDK with CRUDL capabilities. It gets
   *   validated whether it implements the required interfaces.
   */
  public function __construct($controller) {
    if (!$controller instanceof EntityCrudOperationsControllerInterface) {
      throw new InvalidArgumentException(sprintf('Controller must implement %s interface.', EntityCrudOperationsControllerInterface::class));
    }
    if (!$controller instanceof NonPaginatedEntityListingControllerInterface && !$controller instanceof PaginatedEntityListingControllerInterface) {
      throw new InvalidArgumentException(sprintf('Controller must implement either %s or %s interfaces.', NonPaginatedEntityListingControllerInterface::class, PaginatedEntityListingControllerInterface::class));
    }
    $this->controller = $controller;
  }

  /**
   * {@inheritdoc}
   */
  public function create(EntityInterface $entity): void {
    $this->controller->create($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $id): EntityInterface {
    return $this->controller->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function update(EntityInterface $entity): void {
    $this->controller->update($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $id): void {
    // Ignore returned entity object by Apigee Edge.
    $this->controller->delete($id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadAll(): array {
    // Luckily we solved in the PHP API client that even on paginated endpoints
    // all entities can be retrieved with one single method call.
    return $this->controller->getEntities();
  }

}
