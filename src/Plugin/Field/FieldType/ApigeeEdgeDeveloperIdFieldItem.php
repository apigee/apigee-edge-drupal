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

namespace Drupal\apigee_edge\Plugin\Field\FieldType;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Definition of Apigee Edge Developer ID computed field for User entity.
 */
class ApigeeEdgeDeveloperIdFieldItem extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * Computes the values for an item list.
   */
  protected function computeValue() {
    /** @var \Drupal\user\UserInterface $entity */
    $entity = $this->getEntity();

    // Make sure an email address is set.
    // There are cases (registration) where an email might not be set yet.
    if (!$entity->getEmail()) {
      return;
    }

    try {
      /** @var \Drupal\apigee_edge\Entity\Developer $developer */
      $developer = Developer::load($entity->getEmail());
      $value = $developer ? $developer->getDeveloperId() : NULL;

      $this->list[0] = $this->createItem(0, $value);
    }
    catch (\Exception $exception) {
      watchdog_exception('apigee_edge', $exception);
    }
  }

}
