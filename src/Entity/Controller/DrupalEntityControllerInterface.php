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
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides enhancements for Apigee Edge PHP SDK's built in entity controllers.
 */
interface DrupalEntityControllerInterface extends EntityCrudOperationsControllerInterface {

  /**
   * Loads multiple entities.
   *
   * This method works similarly as Drupal entity storage handler's
   * loadMultiple() method. It returns only those entities with the requested
   * ids if they are provided. Controller classes that implements can add
   * additional enhancements, like call different API endpoint if only one
   * id is provided to increase performance.
   *
   * @param array|null $ids
   *   Array of entity ids.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|\Apigee\Edge\Entity\EntityInterface[]
   *   Array of entities that are both Drupal and SDK entities in the same time.
   */
  public function loadMultiple(array $ids = NULL) : array;

  /**
   * Converts a Drupal entity into an SDK entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $drupal_entity
   *   Apigee Edge entity in Drupal.
   *
   * @return \Apigee\Edge\Entity\EntityInterface
   *   Apigee Edge entity in the SDK.
   *
   * @throws \ReflectionException
   */
  public function convertToSdkEntity(EntityInterface $drupal_entity): EdgeEntityInterface;

}
