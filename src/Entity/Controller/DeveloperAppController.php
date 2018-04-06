<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Entity\Controller;

use Apigee\Edge\Api\Management\Controller\AppController;
use Apigee\Edge\Api\Management\Controller\DeveloperAppController as EdgeDeveloperAppController;
use Apigee\Edge\Api\Management\Controller\DeveloperAppControllerInterface as EdgeDeveloperAppControllerInterface;
use Apigee\Edge\Api\Management\Entity\DeveloperApp as EdgeDeveloperApp;
use Apigee\Edge\Entity\EntityInterface;
use Apigee\Edge\Structure\CpsListLimitInterface;
use Drupal\apigee_edge\Entity\Denormalizer\DrupalAppDenormalizer;
use Drupal\apigee_edge\Entity\DeveloperApp;

/**
 * Advanced version of Apigee Edge SDK's developer app controller.
 *
 * It combines the bests of the DeveloperAppController and AppController
 * classes and also provides additional features that the SDK's built in
 * classes.
 *
 * @package Drupal\apigee_edge\Entity\Controller
 */
class DeveloperAppController extends AppController implements DeveloperAppControllerInterface {

  use DrupalEntityControllerAwareTrait;

  /**
   * {@inheritdoc}
   */
  protected function getEntityClass(): string {
    return DeveloperApp::class;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $entityId): EntityInterface {
    return $this->loadApp($entityId);
  }

  /**
   * Creates a developer app controller.
   *
   * @param string $developerId
   *   UUID or email address of a developer.
   *
   * @return \Apigee\Edge\Api\Management\Controller\DeveloperAppControllerInterface
   *   Developer app controller from the SDK.
   */
  protected function createDeveloperAppController(string $developerId): EdgeDeveloperAppControllerInterface {
    return new EdgeDeveloperAppController($this->getOrganisation(), $developerId, $this->client, [new DrupalAppDenormalizer()]);
  }

  /**
   * {@inheritdoc}
   */
  public function create(EntityInterface $entity): void {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $controller = $this->createDeveloperAppController($entity->getDeveloperId());
    $controller->create($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function update(EntityInterface $entity): void {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $controller = $this->createDeveloperAppController($entity->getDeveloperId());
    $controller->update($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $entityId): EntityInterface {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $entity = $this->loadApp($entityId);
    $controller = $this->createDeveloperAppController($entity->getDeveloperId());
    return $controller->delete($entity->getName());
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(CpsListLimitInterface $cpsLimit = NULL): array {
    $developerAppIds = $this->getEntityIds($cpsLimit);
    $apps = $this->listApps(TRUE, $cpsLimit);
    return array_intersect_key($apps, array_flip($developerAppIds));
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIds(CpsListLimitInterface $cpsLimit = NULL): array {
    return $this->listAppIdsByType('developer', $cpsLimit);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByAppName(string $developerId, string $appName) : EntityInterface {
    $controller = $this->createDeveloperAppController($developerId);
    return $controller->load($appName);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntitiesByDeveloper(string $developerId): array {
    /** @var \Apigee\Edge\Api\Management\Controller\DeveloperAppControllerInterface $controller */
    $controller = $this->createDeveloperAppController($developerId);
    return $controller->getEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIdsByDeveloper(string $developerId): array {
    /** @var \Apigee\Edge\Api\Management\Controller\DeveloperAppControllerInterface $controller */
    $controller = $this->createDeveloperAppController($developerId);
    return $controller->getEntityIds();
  }

  /**
   * {@inheritdoc}
   */
  protected function getOriginalEntityClass(): string {
    return EdgeDeveloperApp::class;
  }

}
