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

use Apigee\Edge\Api\Management\Controller\DeveloperController as EdgeDeveloperController;
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Drupal\apigee_edge\Entity\DeveloperInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Advanced version of Apigee Edge SDK's developer controller.
 */
class DeveloperController extends EdgeDeveloperController implements DrupalEntityControllerInterface {
  use DrupalEntityControllerAwareTrait {
    convertToSdkEntity as private privateConvertToSdkEntity;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInterface(): string {
    return DeveloperInterface::class;
  }

  /**
   * {@inheritdoc}
   */
  public function convertToSdkEntity(EntityInterface $drupal_entity): EdgeEntityInterface {
    /** @var \Apigee\Edge\Entity\EntityInterface $entity */
    $entity = $this->privateConvertToSdkEntity($drupal_entity);

    // We use the email address as id to save developer entities, this way
    // we do not need to load the developer by Apigee Edge always.
    // \Drupal\apigee_edge\Entity\Developer::id() always returns the proper
    // email address for this operation.
    $entity->{'set' . $entity->idProperty()}($drupal_entity->id());
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entityId): EdgeEntityInterface {
    $developer = parent::load($entityId);

    /** @var \Apigee\Edge\Entity\EntityInterface $entity */
    $entity = $this->convertToDrupalEntity($developer);
    return $entity;
  }

}
