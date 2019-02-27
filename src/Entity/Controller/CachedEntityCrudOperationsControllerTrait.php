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

use Apigee\Edge\Entity\EntityInterface;

/**
 * Helper trait for those entity controllers that supports all CRUD operations.
 *
 * This trait ensures that the right entity cache method(s) gets called when
 * a CRUD method is called.
 *
 * @see \Apigee\Edge\Controller\EntityCrudOperationsControllerInterface
 */
trait CachedEntityCrudOperationsControllerTrait {

  use EntityCacheAwareControllerTrait;

  /**
   * The decorated entity controller from the SDK.
   *
   * We did not added a return type because this way all entity controller's
   * decorated() method becomes compatible with this declaration.
   *
   * @return \Apigee\Edge\Controller\EntityCrudOperationsControllerInterface
   *   An entity controller that extends this interface.
   */
  abstract protected function decorated();

  /**
   * {@inheritdoc}
   */
  public function create(EntityInterface $entity): void {
    $this->decorated()->create($entity);
    $this->entityCache()->saveEntities([$entity]);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $entity_id): EntityInterface {
    $entity = $this->decorated()->delete($entity_id);
    $this->entityCache()->removeEntities([$entity_id]);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entity_id): EntityInterface {
    $entity = $this->entityCache()->getEntity($entity_id);
    if ($entity === NULL) {
      $entity = $this->decorated()->load($entity_id);
      $this->entityCache()->saveEntities([$entity]);
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function update(EntityInterface $entity): void {
    $this->decorated()->update($entity);
    $this->entityCache()->saveEntities([$entity]);
  }

}
