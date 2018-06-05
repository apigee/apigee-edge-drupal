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
use Apigee\Edge\Normalizer\KeyValueMapNormalizer;
use Apigee\Edge\Structure\AttributesProperty;
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

  private const STATE_KEY_PREFIX = 'api_product_';

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
   * @param \Drupal\Core\State\StateInterface $state
   * @param array $entityNormalizers
   */
  public function __construct(string $organization, ClientInterface $client, StateInterface $state, array $entityNormalizers = []) {
    parent::__construct($organization, $client, $entityNormalizers);
    $this->state = $state;
    $this->keyVMNormalizer = new KeyValueMapNormalizer();
    $this->attributesDenormalizer = new AttributesPropertyDenormalizer();
  }

  /**
   * {@inheritdoc}
   */
  public function updateAttributes(string $entityId, AttributesProperty $attributes): AttributesProperty {
    $this->state->set($this->generateStateKey($entityId), json_encode($this->keyVMNormalizer->normalize($attributes)));
    return $attributes;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(): array {
    /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $entity */
    $entities = parent::getEntities();
    foreach ($entities as $entity) {
      $this->setAttributesFromStates($entity);
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entityId): EntityInterface {
    /** @var \Drupal\apigee_edge\Entity\ApiProductInterface $entity */
    $entity = parent::load($entityId);
    $this->setAttributesFromStates($entity);
  }

  /**
   * Generates a unique tests key for an API product entity id.
   *
   * @param string $entityId
   *   API product entity id.
   *
   * @return string
   *   Unique state id.
   */
  private function generateStateKey(string $entityId) : string {
    return self::STATE_KEY_PREFIX . $entityId;
  }

  /**
   * Sets attributes from States API on an API product entity.
   *
   * @param \Drupal\apigee_edge\Entity\ApiProductInterface $entity
   *   API product entity.
   */
  private function setAttributesFromStates(ApiProductInterface $entity) {
    if ($attributes = $this->state->get($this->generateStateKey($entity->id()))) {
      /** @var \Apigee\Edge\Structure\AttributesProperty $property */
      $property = $this->entityTransformer->denormalize(json_decode($attributes), AttributesProperty::class);
      $entity->setAttributes($property);
    }
  }

}
