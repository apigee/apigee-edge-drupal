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
use Apigee\Edge\Entity\EntityDenormalizer;
use Apigee\Edge\Entity\EntityInterface;
use Apigee\Edge\Entity\EntityNormalizer;
use Apigee\Edge\Structure\CpsListLimitInterface;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\apigee_edge\Entity\DrupalAppDenormalizer;

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
    return new EdgeDeveloperAppController($this->getOrganisation(), $developerId, $this->client, NULL, [new DrupalAppDenormalizer()]);
  }

  /**
   * Converts a Drupal entity into an Edge entity.
   *
   * The reason to do this is because the Drupal query overrides the id()
   * method, so that it works with the listing endpoints. However, using
   * the appId won't work with the create/update/delete endpoints. So in
   * those cases a conversion to the superclass is needed.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperApp $app
   *   Drupal Developer app entity.
   *
   * @return \Apigee\Edge\Api\Management\Entity\DeveloperApp
   *   SDK Developer app entity.
   */
  protected function convertEntity(DeveloperApp $app): EdgeDeveloperApp {
    $normalizer = new EntityNormalizer();
    $denormalizer = new EntityDenormalizer();
    $normalized = $normalizer->normalize($app);
    return $denormalizer->denormalize($normalized, EdgeDeveloperApp::class);
  }

  /**
   * Copies all properties to $destination from $source.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperApp $destination
   *   Drupal developer app entity.
   * @param \Apigee\Edge\Api\Management\Entity\DeveloperApp $source
   *   SDK developer app entity.
   *
   * @throws \ReflectionException
   */
  protected function applyChanges(DeveloperApp $destination, EdgeDeveloperApp $source) {
    $rodst = new \ReflectionObject($destination);
    $rosrc = new \ReflectionObject($source);
    foreach ($rodst->getProperties() as $property) {
      $setter = 'set' . ucfirst($property->getName());
      $getter = 'get' . ucfirst($property->getName());
      if ($rodst->hasMethod($setter) && $rosrc->hasMethod($getter)) {
        $rm = new \ReflectionMethod($destination, $setter);
        $value = $source->{$getter}();
        if ($value !== NULL) {
          $rm->invoke($destination, $value);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function create(EntityInterface $entity): void {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $controller = $this->createDeveloperAppController($entity->getDeveloperId());
    $converted = $this->convertEntity($entity);
    $controller->create($converted);
    $this->applyChanges($entity, $converted);
  }

  /**
   * {@inheritdoc}
   */
  public function update(EntityInterface $entity): void {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $controller = $this->createDeveloperAppController($entity->getDeveloperId());
    $converted = $this->convertEntity($entity);
    $controller->update($converted);
    $this->applyChanges($entity, $converted);
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
   * Loads multiple entities.
   *
   * @param array|null $ids
   *   Array of entity ids.
   *
   * @return \Apigee\Edge\Entity\EntityInterface[]
   *   Array of entities.
   */
  public function loadMultiple(array $ids = NULL): array {
    if ($ids !== NULL && count($ids) === 1) {
      $app = $this->loadApp(reset($ids));
      return [$app->id() => $app];
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

}
