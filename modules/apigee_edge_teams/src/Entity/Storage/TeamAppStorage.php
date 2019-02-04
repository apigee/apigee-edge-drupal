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

namespace Drupal\apigee_edge_teams\Entity\Storage;

use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Entity\Controller\AppControllerInterface;
use Drupal\apigee_edge\Entity\Controller\EdgeEntityControllerInterface;
use Drupal\apigee_edge\Entity\Storage\AppStorage;
use Drupal\apigee_edge_teams\Entity\Controller\TeamAppControllerFactoryInterface;
use Drupal\apigee_edge_teams\Entity\Controller\TeamAppEdgeEntityControllerProxy;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity storage class for Team app entities.
 */
class TeamAppStorage extends AppStorage implements TeamAppStorageInterface {

  /**
   * The team app controller factory.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Controller\TeamAppControllerFactoryInterface
   */
  private $teamAppControllerFactory;

  /**
   * AppStorage constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to be used.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache.
   * @param \Drupal\Component\Datetime\TimeInterface $system_time
   *   The system time.
   * @param \Drupal\apigee_edge_teams\Entity\Controller\TeamAppControllerFactoryInterface $team_app_controller_factory
   *   The team app controller factory service.
   * @param \Drupal\apigee_edge\Entity\Controller\AppControllerInterface $app_controller
   *   The app controller service.
   */
  public function __construct(EntityTypeInterface $entity_type, CacheBackendInterface $cache_backend, MemoryCacheInterface $memory_cache, TimeInterface $system_time, TeamAppControllerFactoryInterface $team_app_controller_factory, AppControllerInterface $app_controller) {
    parent::__construct($entity_type, $cache_backend, $memory_cache, $system_time, $app_controller);
    $this->teamAppControllerFactory = $team_app_controller_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('cache.apigee_edge_entity'),
      $container->get('entity.memory_cache'),
      $container->get('datetime.time'),
      $container->get('apigee_edge_teams.controller.team_app_controller_factory'),
      $container->get('apigee_edge.controller.app')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function entityController(): EdgeEntityControllerInterface {
    return new TeamAppEdgeEntityControllerProxy($this->teamAppControllerFactory, $this->appController);
  }

  /**
   * {@inheritdoc}
   */
  protected function getCacheTagsByOwner(AppInterface $app): array {
    // Add team's name to ensure when the owner of the app (team)
    // gets deleted then _all_ its cached team app data gets purged along with
    // it.
    return ["team:{$app->getAppOwner()}"];
  }

}
