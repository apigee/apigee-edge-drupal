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
use Apigee\Edge\ClientInterface;
use Apigee\Edge\Serializer\EntitySerializerInterface;
use Drupal\apigee_edge\Entity\ApiProductInterface;

/**
 * Advanced version of Apigee Edge SDK's API product controller.
 */
class ApiProductController extends EdgeApiProductController implements DrupalEntityControllerInterface {
  use DrupalEntityControllerAwareTrait;

  /**
   * THE FQCN of the entity class.
   *
   * @var string
   */
  private $entityClass;

  /**
   * ApiProductController constructor.
   *
   * @param string $organization
   *   The organization name.
   * @param \Apigee\Edge\ClientInterface $client
   *   The API client.
   * @param string $entity_class
   *   The FQCN of the entity class that is used in Drupal.
   * @param \Apigee\Edge\Serializer\EntitySerializerInterface|null $entity_serializer
   *   The entity serializer.
   *
   * @throws \ReflectionException
   */
  public function __construct(string $organization, ClientInterface $client, string $entity_class, ?EntitySerializerInterface $entity_serializer = NULL) {
    parent::__construct($organization, $client, $entity_serializer);
    $interface = ApiProductInterface::class;
    $rc = new \ReflectionClass($entity_class);
    if (!$rc->implementsInterface($interface)) {
      throw new \InvalidArgumentException("Entity class must implement {$interface}.");
    }
    $this->entityClass = $entity_class;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityClass(): string {
    return $this->entityClass;
  }

}
