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

use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Drupal\Core\Entity\EntityInterface;

interface EntityConvertInterface {

  /**
   * Converts a Drupal entity into an SDK entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $drupal_entity
   *   Apigee Edge entity in Drupal.
   * @param string $sdkEntityClass
   *   FQCN of the SDK entity class.
   *
   * @return \Apigee\Edge\Entity\EntityInterface
   *   Apigee Edge entity in the SDK.
   */
  public function convertToSdkEntity(EntityInterface $drupal_entity, string $sdkEntityClass): EdgeEntityInterface;

  /**
   * Converts a SDK entity into an Drupal entity.
   *
   * @param \Apigee\Edge\Entity\EntityInterface $sdk_entity
   *   Apigee Edge entity in the SDK.
   * @param string $drupalEntityClass
   *   FQCN of the Drupal entity class.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Apigee Edge entity in the Drupal.
   */
  public function convertToDrupalEntity(EdgeEntityInterface $sdk_entity, string $drupalEntityClass) : EntityInterface;

}
