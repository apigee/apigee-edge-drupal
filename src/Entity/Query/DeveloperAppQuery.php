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

namespace Drupal\apigee_edge\Entity\Query;

use Apigee\Edge\Api\Management\Controller\AppByOwnerControllerInterface;

/**
 * Defines an entity query class for developer app entities.
 */
class DeveloperAppQuery extends AppQueryBase {

  /**
   * {@inheritdoc}
   */
  protected function appOwnerConditionFields() : array {
    return ['developerId', 'email'];
  }

  /**
   * {@inheritdoc}
   */
  protected function appByOwnerController(string $owner): AppByOwnerControllerInterface {
    /** @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppControllerFactoryInterface $dev_app_controller_factory */
    $dev_app_controller_factory = \Drupal::service('apigee_edge.controller.developer_app_controller_factory');
    return $dev_app_controller_factory->developerAppController($owner);
  }

}
