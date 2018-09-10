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

use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
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
   * Sets the entity class that is used by the controller in Drupal.
   *
   * This must be called in all constructors.
   *
   * @param string $entity_class
   *   The FQCN of the entity class.
   *
   * @throws \InvalidArgumentException
   *   If the provided class is not instance of the required interface.
   */
  private function setEntityClass(string $entity_class): void {
    $rc = new \ReflectionClass($entity_class);
    if (!$rc->implementsInterface($this->entityInterface())) {
      throw new \InvalidArgumentException("Entity class must implement {$this->entityInterface()}");
    }
    $this->entityClass = $entity_class;
  }

  /**
   * The FQCN of the interface that the class must extend.
   *
   * @return string
   *   The fully-qualified class name of the interface.
   */
  abstract protected function entityInterface(): string;

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
