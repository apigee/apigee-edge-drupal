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

use Apigee\Edge\Entity\EntityDenormalizer;
use Apigee\Edge\Entity\EntityFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppController;

/**
 * Ensures that loaded apps in Drupal are always Drupal entities.
 *
 * @see \Apigee\Edge\Api\Management\Entity\AppDenormalizer
 */
class DrupalAppDenormalizer extends EntityDenormalizer {

  /**
   * The entity factory.
   *
   * @var \Apigee\Edge\Entity\EntityFactoryInterface
   */
  private $entityFactory;

  /**
   * Constructs a DrupalAppDenormalizer.
   *
   * @param \Apigee\Edge\Entity\EntityFactoryInterface|null $entityFactory
   *   Entity factory.
   */
  public function __construct(EntityFactoryInterface $entityFactory = NULL) {
    parent::__construct();
    // Enforce usage of our own entity factory.
    $this->entityFactory = new DrupalEntityFactory();
  }

  /**
   * @inheritdoc
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (isset($data->developerId)) {
      return parent::denormalize($data, $this->entityFactory->getEntityTypeByController(DeveloperAppController::class));
    }
    // TODO Finish this when Company app support comes to Drupal.
    parent::denormalize($data, $class, $format, $context);
  }

}
