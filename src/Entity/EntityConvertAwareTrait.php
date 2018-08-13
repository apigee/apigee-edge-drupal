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

/**
 * Trait to implement EntityConvertInterface for converting entities.
 */
trait EntityConvertAwareTrait {

  /**
   * {@inheritdoc}
   */
  public static function convertToSdkEntity(EntityInterface $drupal_entity, string $sdk_entity_class): EdgeEntityInterface {
    // Because Drupal entities are the subclasses of SDK entities we can
    // do this. We can not use $this->entitySerializer to transform between
    // Drupal and SDK entities because of Drupal's TypedData system that
    // causes CircularReferenceException by default. If we fix that problem
    // with a custom normalizer we get back a normalized structure that can not
    // denormalized by our entitySerializer without additional workarounds.
    $values = $drupal_entity->toArray();
    // Get rid of useless but also problematic null values.
    $values = array_filter($values, function ($value) {
      return !is_null($value);
    });
    $rc = new \ReflectionClass($sdk_entity_class);
    /** @var \Apigee\Edge\Entity\EntityInterface $sdkEntity */
    $sdk_entity = $rc->newInstance($values);
    return $sdk_entity;
  }

  /**
   * {@inheritdoc}
   */
  public static function convertToDrupalEntity(EdgeEntityInterface $sdk_entity, string $drupal_entity_class): EntityInterface {
    $values = [];
    // The goal is to create an array that is 100% compatible with the
    // structure an SDK entity's constructor can accept that is why we
    // are not calling getter here.
    $ro = new \ReflectionObject($sdk_entity);
    foreach ($ro->getProperties() as $property) {
      $value = NULL;
      $getter = 'get' . ucfirst($property->getName());
      $isser = 'is' . ucfirst($property->getName());
      if ($ro->hasMethod($getter)) {
        $value = $sdk_entity->{$getter}();
      }
      elseif ($ro->hasMethod($isser)) {
        $value = $sdk_entity->{$isser}();
      }
      if ($value !== NULL) {
        $values[$property->getName()] = $value;
      }
    }

    // Get rid of useless but also problematic null values.
    $values = array_filter($values, function ($value) {
      return !is_null($value);
    });
    $rm = new \ReflectionMethod($drupal_entity_class, 'create');
    /** @var \Drupal\Core\Entity\EntityInterface $drupalEntity */
    $drupal_entity = $rm->invoke(NULL, $values);
    // Only mark Drupal entity as new if the SDK entity's ID property is empty.
    $drupal_entity->enforceIsNew(empty($sdk_entity->id()));
    return $drupal_entity;
  }

}
