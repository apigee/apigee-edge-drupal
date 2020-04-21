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

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Entity\EntityInterface as SdkEntityInterface;
use Drupal\Core\Entity\EntityInterface as DrupalEntityInterface;

/**
 * Interface EdgeEntityInterface.
 *
 * The order of extended interfaces matters. SdkEntityInterface::id() is more
 * strict than DrupalEntityInterface::id().
 */
interface EdgeEntityInterface extends SdkEntityInterface, DrupalEntityInterface {

  /**
   * Creates a Drupal entity from an SDK Entity.
   *
   * @param \Apigee\Edge\Entity\EntityInterface $entity
   *   Entity from the PHP SDK.
   *
   * @return \Drupal\apigee_edge\Entity\EdgeEntityInterface
   *   Drupal entity that decorates the SDK entity.
   */
  public static function createFrom(SdkEntityInterface $entity) : self;

  /**
   * Returns all unique ids how an entity can be referenced in Apigee Edge.
   *
   * All these ids can be used in Drupal to load entity as well.
   *
   * Ex.: Developer can be referenced by its UUID or its email address.
   *
   * @return string[]
   *   Array of entity properties that stores unique entity ids. Returned
   *   properties must have a public getter, ex.: 'get' . ucfirst($property).
   */
  public static function uniqueIdProperties() : array;

  /**
   * List of unique ids how an entity can be referenced in Apigee Edge.
   *
   * It must return the values of the properties returned by idProperties().
   *
   * @see \Drupal\apigee_edge\Entity\EdgeEntityInterface::uniqueIdProperties()
   *
   * @return string[]
   *   Array of unique ids on the entity.
   */
  public function uniqueIds(): array;

  /**
   * Returns the decorated SDK entity.
   *
   * THIS IS AN INTERNAL METHOD! You should not do modifications on the
   * decorated entity object, you should use the decorators for this.
   * This method only exists because entity storages uses it.
   *
   * @return \Apigee\Edge\Entity\EntityInterface
   *   The decorated SDK entity.
   *
   * @internal
   */
  public function decorated(): SdkEntityInterface;

}
