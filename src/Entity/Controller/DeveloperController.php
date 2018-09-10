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
use Apigee\Edge\Api\Management\Controller\OrganizationControllerInterface;
use Apigee\Edge\ClientInterface;
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Apigee\Edge\Serializer\EntitySerializerInterface;
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
   * DeveloperController constructor.
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
