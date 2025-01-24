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

namespace Drupal\apigee_edge_apiproduct_rbac_test;

use Apigee\Edge\Api\Management\Entity\ApiProduct;
use Apigee\Edge\Api\Management\Entity\ApiProductInterface;
use Apigee\Edge\Api\Management\Serializer\ApiProductSerializer;
use Apigee\Edge\Entity\EntityInterface;
use Apigee\Edge\Exception\ApiException;
use Apigee\Edge\Structure\AttributesProperty;
use Apigee\Edge\Structure\PagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface;

/**
 * API product controller that reads and writes attributes from/to States API.
 *
 * This speeds up testing because attributes gets saved to Drupal's database
 * rather than Apigee Edge.
 */
final class ApiProductController implements ApiProductControllerInterface {

  private const STATE_API_PRODUCT_KEY_PREFIX = 'api_product_';

  private const STATE_API_PRODUCT_ATTR_KEY_PREFIX = 'api_product_attr_';

  private const STATE_API_PRODUCT_LIST_KEY = 'api_products';

  /**
   * The decorated service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface
   */
  private $innerService;

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private $state;

  /**
   * The entity serializer.
   *
   * @var \Apigee\Edge\Serializer\EntitySerializerInterface
   */
  private $entitySerializer;

  /**
   * ApiProductController constructor.
   *
   * @param \Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface $inner_service
   *   The decorated API product controller service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The States API.
   */
  public function __construct(ApiProductControllerInterface $inner_service, StateInterface $state) {
    $this->innerService = $inner_service;
    $this->state = $state;
    $this->entitySerializer = new ApiProductSerializer();
  }

  /**
   * {@inheritdoc}
   */
  public function create(EntityInterface $entity): void {
    // We still have to create entities on Apigee Edge otherwise they can
    // not be assigned to developer apps (unless they gets mocked too).
    $this->innerService->create($entity);
    /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $entity */
    $this->state->set($this->generateApiProductStateKey($entity->id()), $this->entitySerializer->normalize($entity));
    $this->updateAttributes($entity->id(), $entity->getAttributes());
    $list = $this->state->get(self::STATE_API_PRODUCT_LIST_KEY) ?? [];
    $list[] = $entity->id();
    $this->state->set(self::STATE_API_PRODUCT_LIST_KEY, $list);
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entity_id): EntityInterface {
    $data = $this->state->get($this->generateApiProductStateKey($entity_id));
    if (NULL === $data) {
      throw new ApiException("API Product with {$entity_id} has not found in the storage.");
    }
    /** @var \Drupal\apigee_edge\Entity\ApiProduct $entity */
    $entity = $this->entitySerializer->denormalize($data, ApiProduct::class);
    $this->setAttributesFromStates($entity);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function update(EntityInterface $entity): void {
    $this->state->set($this->generateApiProductStateKey($entity->id()), $this->entitySerializer->normalize($entity));
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $entity_id): EntityInterface {
    // Because we crated API products on Apigee Edge in create() we also have
    // to delete them.
    $this->innerService->delete($entity_id);
    $data = $this->state->get($this->generateApiProductStateKey($entity_id));
    if (NULL === $data) {
      throw new ApiException("API Product with {$entity_id} has not found in the storage.");
    }
    $entity = $this->entitySerializer->denormalize($data, ApiProduct::class);
    $this->state->delete($this->generateApiProductStateKey($entity_id));
    $list = $this->state->get(self::STATE_API_PRODUCT_LIST_KEY) ?? [];
    if ($index = array_search($entity_id, $list)) {
      unset($list[$index]);
    }
    $this->state->set(self::STATE_API_PRODUCT_LIST_KEY, $list);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(?PagerInterface $pager = NULL, string $key_provider = 'id'): array {
    /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $entity */
    $ids = array_map(function ($id) {
      return $this->generateApiProductStateKey($id);
    }, $this->state->get(self::STATE_API_PRODUCT_LIST_KEY) ?? []);
    $entities = [];
    foreach ($this->state->getMultiple($ids) as $data) {
      $entity = $this->entitySerializer->denormalize($data, ApiProduct::class);
      $this->setAttributesFromStates($entity);
      $entities[$entity->id()] = $entity;
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes(string $entity_id): AttributesProperty {
    $data = $this->state->get($this->generateApiProductAttributeStateKey($entity_id)) ?? [];
    return $this->entitySerializer->denormalize($data, AttributesProperty::class);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttributes(string $entity_id, AttributesProperty $attributes): AttributesProperty {
    $this->state->set($this->generateApiProductAttributeStateKey($entity_id), $this->entitySerializer->normalize($attributes));
    return $attributes;
  }

  /**
   * Generates a unique states key for an API product entity.
   *
   * @param string $entity_id
   *   API product entity id.
   *
   * @return string
   *   Unique state id.
   */
  private function generateApiProductStateKey(string $entity_id): string {
    return self::STATE_API_PRODUCT_KEY_PREFIX . $entity_id;
  }

  /**
   * Generates a unique states key for an API product attributes storage.
   *
   * @param string $entity_id
   *   API product entity id.
   *
   * @return string
   *   Unique state id.
   */
  private function generateApiProductAttributeStateKey(string $entity_id): string {
    return self::STATE_API_PRODUCT_ATTR_KEY_PREFIX . $entity_id;
  }

  /**
   * Sets attributes from States API on an API product entity.
   *
   * @param \Apigee\Edge\Api\Management\Entity\ApiProductInterface $entity
   *   API product entity.
   */
  private function setAttributesFromStates(ApiProductInterface $entity) {
    if ($attributes = $this->state->get($this->generateApiProductAttributeStateKey($entity->id()))) {
      /** @var \Apigee\Edge\Structure\AttributesProperty $property */
      $property = $this->entitySerializer->denormalize($attributes, AttributesProperty::class);
      $entity->setAttributes($property);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function searchByAttribute(string $attributeName, string $attributeValue): array {
    return $this->innerService->searchByAttribute($attributeName, $attributeValue);
  }

  /**
   * {@inheritdoc}
   */
  public function getAttribute(string $entityId, string $name): string {
    return $this->innerService->getAttribute($entityId, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttribute(string $entityId, string $name, string $value): string {
    return $this->innerService->updateAttribute($entityId, $name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAttribute(string $entityId, string $name): void {
    $this->innerService->deleteAttribute($entityId, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganisationName(): string {
    $this->innerService->getOrganisationName();
  }

  /**
   * {@inheritdoc}
   */
  public function createPager(int $limit = 0, ?string $startKey = NULL): PagerInterface {
    return $this->innerService->createPager($limit, $startKey);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIds(?PagerInterface $pager = NULL): array {
    return $this->innerService->getEntityIds($pager);
  }

}
