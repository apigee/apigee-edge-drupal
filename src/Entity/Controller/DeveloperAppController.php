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
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Apigee\Edge\Structure\CpsListLimitInterface;
use Drupal\apigee_edge\Entity\AppCredentialStorageAwareTrait;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\apigee_edge\Entity\EntityConvertAwareTrait;
use Drupal\Core\Entity\EntityInterface;

/**
 * Advanced version of Apigee Edge SDK's developer app controller.
 *
 * It combines the bests of the DeveloperAppController and AppController
 * classes and also provides additional features that the SDK's built in
 * classes.
 *
 * We intentionally did not override the getEntityClass() here to get back
 * Drupal developer app entities from SDK controllers. If we would do that
 * then calling $app->getCredentials() here on a Drupal developer app would
 * cause infinite loop.
 *
 * @see \Drupal\apigee_edge\Entity\DeveloperApp::getCredentials()
 *
 * EntityConvertAwareTrait can not be used in the same time with
 * DrupalEntityControllerAwareTrait, because even if we try to alias the
 * first one's convertToDrupalEntity as conflict resolution it in never become
 * compatible with DrupalEntityControllerInterface::convertToSdkEntity.
 * (PHP bug?)
 */
class DeveloperAppController extends AppController implements DeveloperAppControllerInterface {

  use AppCredentialStorageAwareTrait;
  use DrupalEntityControllerAwareTrait;

  /**
   * {@inheritdoc}
   *
   * We had to override this because in this special case
   * parent::getEntityClass() returns an empty string.
   *
   * @see AppController::getEntityClass()
   */
  public function convertToSdkEntity(EntityInterface $drupal_entity): EdgeEntityInterface {
    return EntityConvertAwareTrait::convertToSdkEntity($drupal_entity, EdgeDeveloperApp::class);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function load(string $entityId): EdgeEntityInterface {
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface $app */
    $app = $this->loadApp($entityId);
    // Store loaded credential's in user's private credential store to
    // reduce number of API calls.
    // @see \Drupal\apigee_edge\Entity\DeveloperApp::getCredentials()
    $this->saveAppCredentialsToStorage($app->getDeveloperId(), $app->getName(), $app->getCredentials());
    return EntityConvertAwareTrait::convertToDrupalEntity($app, DeveloperApp::class);
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
    return new EdgeDeveloperAppController($this->getOrganisation(), $developerId, $this->client);
  }

  /**
   * {@inheritdoc}
   */
  public function create(EdgeEntityInterface $entity): void {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $controller = $this->createDeveloperAppController($entity->getDeveloperId());
    $controller->create($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function update(EdgeEntityInterface $entity): void {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $controller = $this->createDeveloperAppController($entity->getDeveloperId());
    $controller->update($entity);
  }

  /**
   * {@inheritdoc}
   *
   * App credential storage entries invalidated in the DeveloperAppStorage.
   *
   * @see \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorage::doDelete()
   */
  public function delete(string $entityId): EdgeEntityInterface {
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
    $apps = array_intersect_key($apps, array_flip($developerAppIds));
    // Store loaded credential's in user's private credential store to
    // reduce number of API calls.
    // @see \Drupal\apigee_edge\Entity\DeveloperApp::getCredentials()
    $converted = array_map(function (EdgeDeveloperApp $app) {
      $this->saveAppCredentialsToStorage($app->getDeveloperId(), $app->getName(), $app->getCredentials());
      return EntityConvertAwareTrait::convertToDrupalEntity($app, DeveloperApp::class);
    }, $apps);
    return $converted;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIds(CpsListLimitInterface $cpsLimit = NULL): array {
    return $this->listAppIdsByType('developer', $cpsLimit);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function loadByAppName(string $developerId, string $appName) : EdgeEntityInterface {
    $controller = $this->createDeveloperAppController($developerId);
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface $app */
    $app = $controller->load($appName);
    // Store loaded credential's in user's private credential store to
    // reduce number of API calls.
    // @see \Drupal\apigee_edge\Entity\DeveloperApp::getCredentials()
    $this->saveAppCredentialsToStorage($app->getDeveloperId(), $app->getName(), $app->getCredentials());
    return EntityConvertAwareTrait::convertToDrupalEntity($app, DeveloperApp::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntitiesByDeveloper(string $developerId): array {
    /** @var \Apigee\Edge\Api\Management\Controller\DeveloperAppControllerInterface $controller */
    $controller = $this->createDeveloperAppController($developerId);
    $apps = $controller->getEntities();
    // Store loaded credential's in user's private credential store to
    // reduce number of API calls.
    // @see \Drupal\apigee_edge\Entity\DeveloperApp::getCredentials()
    $converted = array_map(function (EdgeDeveloperApp $app) {
      $this->saveAppCredentialsToStorage($app->getDeveloperId(), $app->getName(), $app->getCredentials());
      return EntityConvertAwareTrait::convertToDrupalEntity($app, DeveloperApp::class);
    }, $apps);
    return $converted;
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
