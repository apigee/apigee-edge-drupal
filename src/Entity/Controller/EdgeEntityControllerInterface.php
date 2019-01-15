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
 * Provides a unified way for making CRUDL APIs calls to Apigee Edge.
 *
 * Every Apigee Edge entity that would like to be a full-flagged Drupal
 * entity must support these basic operations, because this is the minimum that
 * entity storages requires.
 *
 * (Although these operations can throw an exception or do nothing if
 * some operation is not supporter. This could occur in case of Monetization
 * entities, ex.: an API package can not be deleted or updated.)
 */
interface EdgeEntityControllerInterface {

  /**
   * Creates an entity in Apigee Edge.
   *
   * Applies incoming values from Apigee Edge in $entity.
   *
   * @param \Apigee\Edge\Entity\EntityInterface $entity
   *   The created entity.
   */
  public function create(EntityInterface $entity): void;

  /**
   * Loads an entity from Apigee Edge.
   *
   * @param string $id
   *   One of an entity's unique ids. (Some entities has more than one unique
   *   id at a moment, ex.: developer's email address and id (UUID).)
   *
   * @return \Apigee\Edge\Entity\EntityInterface
   *   The load entity from Apigee Edge.
   *
   * @throws \Apigee\Edge\Exception\ApiException
   *   If entity does not exist with id.
   */
  public function load(string $id): EntityInterface;

  /**
   * Updates an entity in Apigee Edge.
   *
   * Applies incoming values from Apigee Edge in $entity.
   *
   * @param \Apigee\Edge\Entity\EntityInterface $entity
   *   The update entity.
   */
  public function update(EntityInterface $entity): void;

  /**
   * Removes an entity from Apigee Edge.
   *
   * @param string $id
   *   One of an entity's unique ids. (Some entities has more than one unique
   *   id at a moment, ex.: developer's email address and id (UUID).)
   */
  public function delete(string $id): void;

  /**
   * Loads _all_ entities from Apigee Edge.
   *
   * All entities, even on pagination enabled endpoints, this method must
   * return all entities even it requires multiple API calls.
   *
   * @return \Apigee\Edge\Entity\EntityInterface[]
   *   Array of entities.
   */
  public function loadAll(): array;

}
