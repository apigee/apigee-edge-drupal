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

use Apigee\Edge\Api\Management\Controller\OrganizationControllerInterface;
use Apigee\Edge\ClientInterface;
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Apigee\Edge\Serializer\EntitySerializerInterface;
use Drupal\apigee_edge\Entity\EntityConvertAwareTrait;
use Drupal\Core\Entity\EntityInterface;

/**
 * Contains general implementations for Drupal entity controllers.
 *
 * @see \Drupal\apigee_edge\Entity\Controller\DrupalEntityControllerInterface
 */
trait DrupalEntityControllerAwareTrait {

  /**
   * The FQCN of the Drupal entity class.
   *
   * @var string
   */
  protected $entityClass;

  /**
   * {@inheritdoc}
   */
  public function __construct(string $organization, ClientInterface $client, string $entity_class, ?EntitySerializerInterface $entity_serializer = NULL, ?OrganizationControllerInterface $organization_controller = NULL) {
    parent::__construct($organization, $client, $entity_serializer, $organization_controller);
    $rc = new \ReflectionClass($entity_class);
    $interface = $this->getInterface();
    if (!$rc->implementsInterface($interface)) {
      throw new \InvalidArgumentException("Entity class must implement {$interface}");
    }
    $this->entityClass = $entity_class;
  }

  /**
   * The interface class of the current entity.
   *
   * @return string
   *   Interface class.
   */
  abstract protected function getInterface(): string;

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL): array {
    if ($ids !== NULL && count($ids) === 1) {
      /** @var \Apigee\Edge\Entity\EntityInterface $entity */
      $entity = $this->load(reset($ids));
      return [$entity->id() => $this->convertToDrupalEntity($entity)];
    }

    $allEntities = array_map(function (EdgeEntityInterface $entity): EntityInterface {
      return $this->convertToDrupalEntity($entity);
    }, $this->getEntities());
    if ($ids === NULL) {
      return $allEntities;
    }

    return array_intersect_key($allEntities, array_flip($ids));
  }

  /**
   * Converts a Drupal entity into an SDK entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $drupal_entity
   *   Apigee Edge entity in Drupal.
   *
   * @return \Apigee\Edge\Entity\EntityInterface
   *   Apigee Edge entity in the SDK.
   */
  public function convertToSdkEntity(EntityInterface $drupal_entity): EdgeEntityInterface {
    return EntityConvertAwareTrait::convertToSdkEntity($drupal_entity, $this->getEntityClass());
  }

  /**
   * Converts an SDK entity into a Drupal entity.
   *
   * @param \Apigee\Edge\Entity\EntityInterface $sdk_entity
   *   Apigee Edge entity in the SDK.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Apigee Edge entity in Drupal.
   */
  public function convertToDrupalEntity(EdgeEntityInterface $sdk_entity): EntityInterface {
    return EntityConvertAwareTrait::convertToDrupalEntity($sdk_entity, $this->entityClass);
  }

}
