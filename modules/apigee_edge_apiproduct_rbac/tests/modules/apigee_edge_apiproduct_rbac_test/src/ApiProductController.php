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

use Apigee\Edge\ClientInterface;
use Apigee\Edge\Denormalizer\AttributesPropertyDenormalizer;
use Apigee\Edge\Entity\EntityInterface;
use Apigee\Edge\Exception\ApiException;
use Apigee\Edge\Normalizer\KeyValueMapNormalizer;
use Apigee\Edge\Structure\AttributesProperty;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\apigee_edge\Entity\Controller\ApiProductController as OriginalApiProductController;
use Drupal\Core\State\StateInterface;

/**
 * API product controller that reads and writes attributes from/to States API.
 *
 * This speeds up testing because attributes gets saved to Drupal's database
 * rather than Apigee Edge.
 */
final class ApiProductController extends OriginalApiProductController {

  private const STATE_API_PRODUCT_KEY_PREFIX = 'api_product_';
  private const STATE_API_PRODUCT_ATTR_KEY_PREFIX = 'api_product_attr_';
  private const STATE_API_PRODUCT_LIST_KEY = 'api_products';

  /**
   * @var \Drupal\Core\State\StateInterface*/
  private $state;

  /**
   * @var \Apigee\Edge\Normalizer\KeyValueMapNormalizer*/
  private $keyVMNormalizer;

  /**
   * @var \Apigee\Edge\Denormalizer\AttributesPropertyDenormalizer*/
  private $attributesDenormalizer;

  /**
   * ApiProductController constructor.
   *
   * @param string $organization
   * @param \Apigee\Edge\ClientInterface $client
   * @param string $entityClass
   * @param \Drupal\Core\State\StateInterface $state
   * @param array $entityNormalizers
   *
   * @throws \ReflectionException
   */
  public function __construct(string $organization, ClientInterface $client, string $entityClass, StateInterface $state, array $entityNormalizers = []) {
    parent::__construct($organization, $client, $entityClass, $entityNormalizers);
    $this->state = $state;
    $this->keyVMNormalizer = new KeyValueMapNormalizer();
    $this->attributesDenormalizer = new AttributesPropertyDenormalizer();
  }

  /**
   * {@inheritdoc}
   */
  public function create(EntityInterface $entity): void {
    // We still have to create entities on Apigee Edge otherwise they can
    // not be assigned to developer apps (unless they gets mocked too).
    parent::create($entity);
    /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $entity */
    $this->state->set($this->generateApiProductStateKey($entity->id()), $this->entityTransformer->normalize($entity));
    $this->updateAttributes($entity->id(), $entity->getAttributes());
    $list = $this->state->get(self::STATE_API_PRODUCT_LIST_KEY) ?? [];
    $list[] = $entity->id();
    $this->state->set(self::STATE_API_PRODUCT_LIST_KEY, $list);
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entityId): EntityInterface {
    $data = $this->state->get($this->generateApiProductStateKey($entityId));
    if (NULL === $data) {
      throw new ApiException("API Product with {$entityId} has not found in the storage.");
    }
    /** @var \Drupal\apigee_edge\Entity\ApiProduct $entity */
    $entity = $this->entityTransformer->denormalize($data, ApiProduct::class);
    $this->setAttributesFromStates($entity);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function update(EntityInterface $entity): void {
    $this->state->set($this->generateApiProductStateKey($entity->id()), $this->entityTransformer->normalize($entity));
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $entityId): EntityInterface {
    $data = $this->state->get($this->generateApiProductStateKey($entityId));
    if (NULL === $data) {
      throw new ApiException("API Product with {$entityId} has not found in the storage.");
    }
    $entity = $this->entityTransformer->denormalize($data, ApiProduct::class);
    $this->state->delete($this->generateApiProductStateKey($entityId));
    $list = $this->state->get(self::STATE_API_PRODUCT_LIST_KEY) ?? [];
    if ($index = array_search($entityId, $list)) {
      unset($list[$index]);
    }
    $this->state->set(self::STATE_API_PRODUCT_LIST_KEY, $list);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(): array {
    /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $entity */
    $ids = array_map(function ($id) {
      return $this->generateApiProductStateKey($id);
    }, $this->state->get(self::STATE_API_PRODUCT_LIST_KEY) ?? []);
    $entities = [];
    foreach ($this->state->getMultiple($ids) as $data) {
      $entity = $this->entityTransformer->denormalize($data, ApiProduct::class);
      $this->setAttributesFromStates($entity);
      $entities[$entity->id()] = $entity;
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes(string $entityId): AttributesProperty {
    $data = $this->state->get($this->generateApiProductAttributeStateKey($entityId)) ?? [];
    return $this->entityTransformer->denormalize($data, AttributesProperty::class);
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttributes(string $entityId, AttributesProperty $attributes): AttributesProperty {
    $this->state->set($this->generateApiProductAttributeStateKey($entityId), $this->keyVMNormalizer->normalize($attributes));
    return $attributes;
  }

  /**
   * Generates a unique states key for an API product entity.
   *
   * @param string $entityId
   *   API product entity id.
   *
   * @return string
   *   Unique state id.
   */
  private function generateApiProductStateKey(string $entityId) : string {
    return self::STATE_API_PRODUCT_KEY_PREFIX . $entityId;
  }

  /**
   * Generates a unique states key for an API product attributes storage.
   *
   * @param string $entityId
   *   API product entity id.
   *
   * @return string
   *   Unique state id.
   */
  private function generateApiProductAttributeStateKey(string $entityId) : string {
    return self::STATE_API_PRODUCT_ATTR_KEY_PREFIX . $entityId;
  }

  /**
   * Sets attributes from States API on an API product entity.
   *
   * @param \Drupal\apigee_edge\Entity\ApiProductInterface $entity
   *   API product entity.
   */
  private function setAttributesFromStates(ApiProductInterface $entity) {
    if ($attributes = $this->state->get($this->generateApiProductAttributeStateKey($entity->id()))) {
      /** @var \Apigee\Edge\Structure\AttributesProperty $property */
      $property = $this->entityTransformer->denormalize($attributes, AttributesProperty::class);
      $entity->setAttributes($property);
    }
  }

}
