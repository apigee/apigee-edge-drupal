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

use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Drupal\apigee_edge\Entity\Controller\ApiProductController;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for API products.
 */
class ApiProductStorage extends EdgeEntityStorageBase implements ApiProductStorageInterface {

  /**
   * Constructs an APIProductStorage instance.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdkConnector
   *   The SDK connector service.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to be used.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger to be used.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Configuration factory.
   * @param \Drupal\Component\Datetime\TimeInterface $systemTime
   *   System time.
   */
  public function __construct(SDKConnectorInterface $sdkConnector, EntityTypeInterface $entity_type, CacheBackendInterface $cache, LoggerInterface $logger, ConfigFactoryInterface $config, TimeInterface $systemTime) {
    parent::__construct($sdkConnector, $entity_type, $cache, $logger, $systemTime);
    $this->cacheExpiration = $config->get('apigee_edge.api_product_settings')->get('cache_expiration');
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.apigee_edge');
    return new static(
      $container->get('apigee_edge.sdk_connector'),
      $entity_type,
      $container->get('cache.apigee_edge_entity'),
      $logger,
      $container->get('config.factory'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getController(SDKConnectorInterface $connector) : EntityCrudOperationsControllerInterface {
    return new ApiProductController($connector->getOrganization(), $connector->getClient());
  }

}
