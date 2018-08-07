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
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL): array {
    if ($ids !== NULL && count($ids) === 1) {
      $entity = $this->load(reset($ids));
      return [$entity->id() => $entity];
    }

    $allEntities = $this->getEntities();
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
    return EntityConvertAwareTrait::convertToSdkEntity($drupal_entity, parent::getEntityClass());
  }

}
