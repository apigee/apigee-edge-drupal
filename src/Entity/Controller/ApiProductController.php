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
use Apigee\Edge\Entity\EntityInterface;
use Apigee\Edge\Structure\AttributesProperty;
use Apigee\Edge\Structure\PagerInterface;
use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Definition of the API product controller service.
 *
 * This integrates the Management API's API product controller from the
 * SDK's with Drupal.
 */
final class ApiProductController implements ApiProductControllerInterface {

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
   * ApiProductController constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   */
  public function __construct(SDKConnectorInterface $connector) {
    $this->connector = $connector;
  }

  /**
   * Returns the decorated API product controller from the SDK.
   *
   * @return \Apigee\Edge\Api\Management\Controller\ApiProductControllerInterface
   *   The initialized API product controller.
   */
  private function decorated(): EdgeApiProductControllerInterface {
    if ($this->instance === NULL) {
      $this->instance = new EdgeApiProductController($this->connector->getOrganization(), $this->connector->getClient());
    }

    return $this->instance;
  }

  /**
   * {@inheritdoc}
   */
  public function searchByAttribute(string $attributeName, string $attributeValue): array {
    return $this->decorated()->searchByAttribute($attributeName, $attributeValue);
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes(string $entityId): AttributesProperty {
    return $this->decorated()->getAttributes($entityId);
  }

  /**
   * {@inheritdoc}
   */
  public function getAttribute(string $entityId, string $name): string {
    return $this->decorated()->getAttribute($entityId, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttributes(string $entityId, AttributesProperty $attributes): AttributesProperty {
    return $this->decorated()->updateAttributes($entityId, $attributes);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttribute(string $entityId, string $name, string $value): string {
    return $this->decorated()->updateAttribute($entityId, $name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAttribute(string $entityId, string $name): void {
    $this->decorated()->deleteAttribute($entityId, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function create(EntityInterface $entity): void {
    $this->decorated()->create($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $entityId): EntityInterface {
    return $this->decorated()->delete($entityId);
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entityId): EntityInterface {
    return $this->decorated()->load($entityId);
  }

  /**
   * {@inheritdoc}
   */
  public function update(EntityInterface $entity): void {
    $this->decorated()->update($entity);
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
  public function createPager(int $limit = 0, ?string $startKey = NULL): PagerInterface {
    return $this->decorated()->createPager($limit, $startKey);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIds(PagerInterface $pager = NULL): array {
    return $this->decorated()->getEntityIds($pager);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(PagerInterface $pager = NULL, string $key_provider = 'id'): array {
    return $this->decorated()->getEntities($pager, $key_provider);
  }

}
