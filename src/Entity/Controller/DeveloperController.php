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

use Apigee\Edge\Api\Management\Controller\DeveloperController as EdgeDeveloperController;
use Apigee\Edge\Api\Management\Entity\DeveloperInterface;
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Apigee\Edge\Structure\AttributesProperty;
use Apigee\Edge\Structure\PagerInterface;
use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Definition of the Developer controller service.
 *
 * This integrates the Management API's Developer controller from the
 * SDK's with Drupal.
 *
 * TODO Cache developers in a memory cache.
 * This could be useful to figure out a developer Id of an already cached
 * developer from its email address where it is needed. Ex.: app controller,
 * apigee_edge_developer_id field, etc.
 */
final class DeveloperController implements DeveloperControllerInterface {

  /**
   * The decorated developer controller from the SDK.
   *
   * @var \Apigee\Edge\Api\Management\Controller\DeveloperController
   */
  private $decorated;

  /**
   * DeveloperController constructor.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK connector service.
   * @param \Drupal\apigee_edge\Entity\Controller\OrganizationControllerInterface $org_controller
   *   The organization controller service.
   */
  public function __construct(SDKConnectorInterface $connector, OrganizationControllerInterface $org_controller) {
    $this->decorated = new EdgeDeveloperController($connector->getOrganization(), $connector->getClient(), NULL, $org_controller);
  }

  /**
   * {@inheritdoc}
   */
  public function getDeveloperByApp(string $appName): DeveloperInterface {
    return $this->decorated->getDeveloperByApp($appName);
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes(string $entityId): AttributesProperty {
    return $this->decorated->getAttributes($entityId);
  }

  /**
   * {@inheritdoc}
   */
  public function getAttribute(string $entityId, string $name): string {
    return $this->decorated->getAttribute($entityId, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttributes(string $entityId, AttributesProperty $attributes): AttributesProperty {
    return $this->decorated->updateAttributes($entityId, $attributes);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttribute(string $entityId, string $name, string $value): string {
    return $this->decorated->updateAttribute($entityId, $name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAttribute(string $entityId, string $name): void {
    $this->decorated->deleteAttribute($entityId, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function create(EdgeEntityInterface $entity): void {
    $this->decorated->create($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $entityId): EdgeEntityInterface {
    return $this->decorated->delete($entityId);
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entityId): EdgeEntityInterface {
    return $this->decorated->load($entityId);
  }

  /**
   * {@inheritdoc}
   */
  public function update(EdgeEntityInterface $entity): void {
    $this->decorated->update($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganisationName(): string {
    return $this->decorated->getOrganisationName();
  }

  /**
   * {@inheritdoc}
   */
  public function createPager(int $limit = 0, ?string $startKey = NULL): PagerInterface {
    return $this->decorated->createPager($limit, $startKey);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIds(PagerInterface $pager = NULL): array {
    return $this->decorated->getEntityIds($pager);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(PagerInterface $pager = NULL, string $key_provider = 'id'): array {
    return $this->decorated->getEntities($pager, $key_provider);
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus(string $entityId, string $status): void {
    $this->decorated->setStatus($entityId, $status);
  }

}
