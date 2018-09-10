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

use Apigee\Edge\Api\Management\Controller\ApiProductController as EdgeApiProductController;
use Apigee\Edge\Api\Management\Controller\OrganizationControllerInterface;
use Apigee\Edge\ClientInterface;
use Apigee\Edge\Serializer\EntitySerializerInterface;
use Drupal\apigee_edge\Entity\ApiProductInterface;

/**
 * Advanced version of Apigee Edge SDK's API product controller.
 */
class ApiProductController extends EdgeApiProductController implements DrupalEntityControllerInterface {
  use DrupalEntityControllerAwareTrait;

  /**
   * ApiProductController constructor.
   *
   * @param string $organization
   *   Name of the organization.
   * @param \Apigee\Edge\ClientInterface $client
   *   The API client.
   * @param string $entity_class
   *   The FQCN of the entity class used by this controller.
   * @param \Apigee\Edge\Serializer\EntitySerializerInterface|null $entity_serializer
   *   The entity serializer.
   * @param \Apigee\Edge\Api\Management\Controller\OrganizationControllerInterface|null $organization_controller
   *   The organization controller.
   */
  public function __construct(string $organization, ClientInterface $client, string $entity_class, ?EntitySerializerInterface $entity_serializer = NULL, ?OrganizationControllerInterface $organization_controller = NULL) {
    parent::__construct($organization, $client, $entity_serializer, $organization_controller);
    $this->setEntityClass($entity_class);
  }

  /**
   * {@inheritdoc}
   */
  protected function entityInterface(): string {
    return ApiProductInterface::class;
  }

}
