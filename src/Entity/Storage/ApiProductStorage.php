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

namespace Drupal\apigee_edge\Entity\Storage;

use Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface;
use Drupal\apigee_edge\Entity\Controller\CachedManagementApiEdgeEntityControllerProxy;
use Drupal\apigee_edge\Entity\Controller\EdgeEntityControllerInterface;
use Drupal\apigee_edge\Entity\Controller\EntityCacheAwareControllerInterface;
use Drupal\apigee_edge\Entity\Controller\ManagementApiEdgeEntityControllerProxy;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity storage class for API product entities.
 */
class ApiProductStorage extends EdgeEntityStorageBase implements ApiProductStorageInterface {

  /**
   * The API product controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface
   */
  private $apiProductController;

  /**
   * Constructs an APIProductStorage instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to be used.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache.
   * @param \Drupal\Component\Datetime\TimeInterface $system_time
   *   The system time.
   * @param \Drupal\apigee_edge\Entity\Controller\ApiProductControllerInterface $api_product_controller
   *   The API product controller service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Configuration factory.
   */
  public function __construct(EntityTypeInterface $entity_type, CacheBackendInterface $cache_backend, MemoryCacheInterface $memory_cache, TimeInterface $system_time, ApiProductControllerInterface $api_product_controller, ConfigFactoryInterface $config) {
    parent::__construct($entity_type, $cache_backend, $memory_cache, $system_time);
    $this->cacheExpiration = $config->get('apigee_edge.api_product_settings')->get('cache_expiration');
    $this->apiProductController = $api_product_controller;
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
      $container->get('apigee_edge.controller.api_product'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function entityController(): EdgeEntityControllerInterface {
    if ($this->apiProductController instanceof EntityCacheAwareControllerInterface) {
      return new CachedManagementApiEdgeEntityControllerProxy($this->apiProductController);
    }
    return new ManagementApiEdgeEntityControllerProxy($this->apiProductController);
  }

}
