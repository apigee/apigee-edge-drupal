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

namespace Drupal\apigee_edge_teams\Entity\Controller;

use Apigee\Edge\Api\Management\Entity\AppInterface;
use Apigee\Edge\Api\Management\Entity\CompanyAppInterface;
use Apigee\Edge\Entity\EntityInterface;
use Drupal\apigee_edge\Entity\Controller\AppControllerInterface;
use Drupal\apigee_edge\Entity\Controller\EdgeEntityControllerInterface;
use Drupal\apigee_edge\Exception\RuntimeException;

/**
 * Team app specific entity controller implementation.
 *
 * It ensures that the right SDK controllers (and with that the right API
 * endpoints) gets used for CRUDL operations.
 */
final class TeamAppEdgeEntityControllerProxy implements EdgeEntityControllerInterface {

  /**
   * The team app controller factory service.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Controller\TeamAppControllerFactoryInterface
   */
  private $teamAppControllerFactory;

  /**
   * The app controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\AppControllerInterface
   */
  private $appController;

  /**
   * TeamAppEdgeEntityControllerProxy constructor.
   *
   * @param \Drupal\apigee_edge_teams\Entity\Controller\TeamAppControllerFactoryInterface $team_app_controller_factory
   *   The team app controller factory service.
   * @param \Drupal\apigee_edge\Entity\Controller\AppControllerInterface $app_controller
   *   The app controller service.
   */
  public function __construct(TeamAppControllerFactoryInterface $team_app_controller_factory, AppControllerInterface $app_controller) {
    $this->teamAppControllerFactory = $team_app_controller_factory;
    $this->appController = $app_controller;
  }

  /**
   * {@inheritdoc}
   */
  public function create(EntityInterface $entity): void {
    /** @var \Apigee\Edge\Api\Management\Entity\CompanyAppInterface $entity */
    if (empty($entity->getCompanyName())) {
      // Sanity check.
      throw new RuntimeException('Company name has to set on the app.');
    }
    $controller = $this->teamAppControllerFactory->teamAppController($entity->getCompanyName());
    $controller->create($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $id): EntityInterface {
    return $this->appController->loadApp($id);
  }

  /**
   * {@inheritdoc}
   */
  public function update(EntityInterface $entity): void {
    /** @var \Apigee\Edge\Api\Management\Entity\CompanyAppInterface $entity */
    $controller = $this->teamAppControllerFactory->teamAppController($entity->getCompanyName());
    $controller->update($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $id): void {
    // Try to be smart here and load the app from the company app
    // entity cache (app controller's cache is probably empty unless there were
    // a load() or getEntities() call before).
    $entity = \Drupal::entityTypeManager()->getStorage('team_app')->load($id);
    if (!$entity) {
      // Entity has not found in the entity cache, we have it from Apigee Edge.
      $entity = $this->load($id);
    }
    /** @var \Apigee\Edge\Api\Management\Entity\CompanyAppInterface $entity */
    $controller = $this->teamAppControllerFactory->teamAppController($entity->getCompanyName());
    // The id that we got is a UUID, what we need is an app name.
    $controller->delete($entity->getName());
  }

  /**
   * {@inheritdoc}
   */
  public function loadAll(): array {
    return array_filter($this->appController->listApps(TRUE), function (AppInterface $app) {
      return $app instanceof CompanyAppInterface;
    });
  }

}
