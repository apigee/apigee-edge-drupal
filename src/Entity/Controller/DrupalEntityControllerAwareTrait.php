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
  public function loadMultiple(array $ids = NULL) : array {
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
   * {@inheritdoc}
   */
  public function convertToSdkEntity(EntityInterface $drupal_entity): EdgeEntityInterface {
    // Because Drupal entities are the subclasses of SDK entities we can
    // do this. We can not use $this->entityTransformer to transform between
    // Drupal and SDK entities because of Drupal's TypedData system that
    // causes CircularReferenceException by default. If we fix that problem
    // with a custom normalizer we get back a normalized structure that can not
    // denormalized by our entityTransformer without additional workarounds.
    $values = $drupal_entity->toArray();
    // Get rid of useless but also problematic null values.
    $values = array_filter($values, function ($value) {
      return !is_null($value);
    });
    $rc = new \ReflectionClass($this->getOriginalEntityClass());
    /** @var \Apigee\Edge\Entity\EntityInterface $sdkEntity */
    $sdkEntity = $rc->newInstance($values);
    return $sdkEntity;
  }

  /**
   * Returns the fully-qualified class name of the original SDK entity.
   *
   * @return string
   *   The FQCN of the original SDK entity.
   */
  protected function getOriginalEntityClass(): string {
    return parent::getEntityClass();
  }

}
