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

use Apigee\Edge\Exception\ApiException;
use Drupal\apigee_edge\Entity\Controller\CachedManagementApiEdgeEntityControllerProxy;
use Drupal\apigee_edge\Entity\Controller\EdgeEntityControllerInterface;
use Drupal\apigee_edge\Entity\Controller\EntityCacheAwareControllerInterface;
use Drupal\apigee_edge\Entity\Controller\ManagementApiEdgeEntityControllerProxy;
use Drupal\apigee_edge\Entity\Storage\AttributesAwareFieldableEdgeEntityStorageBase;
use Drupal\apigee_edge_teams\Entity\Controller\TeamControllerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity storage implementation for teams.
 */
class TeamStorage extends AttributesAwareFieldableEdgeEntityStorageBase implements TeamStorageInterface {

  /**
   * The team controller service.
   *
   * @var \Drupal\apigee_edge_teams\Entity\Controller\TeamControllerInterface
   */
  private $teamController;

  /**
   * Constructs an DeveloperStorage instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to be used.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache.
   * @param \Drupal\Component\Datetime\TimeInterface $system_time
   *   The system time.
   * @param \Drupal\apigee_edge_teams\Entity\Controller\TeamControllerInterface $team_controller
   *   The team controller service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Configuration factory.
   */
  public function __construct(EntityTypeInterface $entity_type, CacheBackendInterface $cache_backend, MemoryCacheInterface $memory_cache, TimeInterface $system_time, TeamControllerInterface $team_controller, ConfigFactoryInterface $config) {
    parent::__construct($entity_type, $cache_backend, $memory_cache, $system_time);
    $this->teamController = $team_controller;
    $this->cacheExpiration = $config->get('apigee_edge_teams.team_settings')->get('cache_expiration');
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
      $container->get('apigee_edge_teams.controller.team'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function entityController(): EdgeEntityControllerInterface {
    if ($this->teamController instanceof EntityCacheAwareControllerInterface) {
      return new CachedManagementApiEdgeEntityControllerProxy($this->teamController);
    }
    return new ManagementApiEdgeEntityControllerProxy($this->teamController);
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInterface $entity */
    $developer_status = $entity->getStatus();
    $result = parent::doSave($id, $entity);

    // Change the status of the team (company) in Apigee Edge.
    // TODO Only change it if it has changed.
    try {
      $this->teamController->setStatus($entity->id(), $developer_status);
    }
    catch (ApiException $exception) {
      throw new EntityStorageException($exception->getMessage(), $exception->getCode(), $exception);
    }
    // Apply status change in the entity object as well.
    $entity->setStatus($developer_status);

    return $result;
  }

}
