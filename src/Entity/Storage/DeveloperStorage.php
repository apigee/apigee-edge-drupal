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

use Apigee\Edge\Api\Management\Controller\DeveloperControllerInterface;
use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperController;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for developers.
 */
class DeveloperStorage extends EdgeEntityStorageBase implements DeveloperStorageInterface {

  /**
   * @var \Drupal\Core\Database\Connection*/
  protected $database;

  /**
   * Constructs an DeveloperStorage instance.
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
    $this->cacheExpiration = $config->get('apigee_edge.developer_settings')->get('cache_expiration');
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
  public function getController(SDKConnectorInterface $connector): EntityCrudOperationsControllerInterface {
    return new DeveloperController($connector->getOrganization(), $connector->getClient());
  }

  /**
   * {@inheritdoc}
   *
   * We had to override this function because a developer can be referenced
   * by email or developer id (UUID) on Apigee Edge. In Drupal we use the email
   * as primary and because of that if we try to load a developer by UUID then
   * we get back an integer because EntityStorageBase::loadMultiple() returns
   * an array where entities keyed by their Drupal ids.
   *
   * @see \Drupal\Core\Entity\EntityStorageBase::loadMultiple()
   */
  public function loadMultiple(array $ids = NULL) {
    $entities = parent::loadMultiple($ids);
    if ($ids) {
      $entitiesByDeveloperId = [];
      foreach ($entities as $entity) {
        // It could be integer if ids were UUIDs.
        if (is_object($entity)) {
          /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $entity */
          $entitiesByDeveloperId[$entity->getDeveloperId()] = $entity;
        }
      }
      $entities = array_merge($entities, $entitiesByDeveloperId);
      $requestedEntities = [];
      // Ensure that the returned array is ordered the same as the original
      // $ids array if this was passed in and remove any invalid ids.
      $passedIds = array_flip(array_intersect_key(array_flip($ids), $entities));
      foreach ($passedIds as $id) {
        $requestedEntities[$id] = $entities[$id];
      }
      $entities = $requestedEntities;
    }
    else {
      // Remove duplicates because it could happen that one entity is
      // referenced in this array both with its email (Drupal ID) and developer
      // id (UUID).
      $entities = array_filter($entities, function ($entity, $key) {
        /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $entity */
        return $entity->getEmail() === $key;
      }, ARRAY_FILTER_USE_BOTH);
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\Developer $entity */
    $developer_status = $entity->getStatus();
    $result = parent::doSave($id, $entity);

    // In case of entity update, the original email must be
    // replaced by the new email before a new API call.
    if ($result === SAVED_UPDATED) {
      $entity->setOriginalEmail($entity->getEmail());
    }
    $this->withController(function (DeveloperControllerInterface $controller) use ($entity, $developer_status) {
      $controller->setStatus($entity->id(), $developer_status);
      $entity->setStatus($developer_status);
    });

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPersistentCacheTags(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\Developer $entity */
    $cacheTags = parent::getPersistentCacheTags($entity);
    $cacheTags = array_map(function ($cid) use ($entity) {
      // Sanitize accented characters in developer's email addresses.
      return str_replace($entity->id(), filter_var($entity->id(), FILTER_SANITIZE_ENCODED), $cid);
    }, $cacheTags);
    // Add developerId (besides email address) as a cache tag too.
    $cacheTags[] = "{$this->entityTypeId}:{$entity->uuid()}";
    $cacheTags[] = "{$this->entityTypeId}:{$entity->uuid()}:values";
    // Also add Drupal user id to ensure that cached developer data is
    // invalidated when the related Drupal user has changed or deleted.
    if ($entity->getOwnerId()) {
      $cacheTags[] = "user:{$entity->getOwnerId()}";
    }
    return $cacheTags;
  }

  /**
   * {@inheritdoc}
   */
  protected function setPersistentCache(array $entities) {
    parent::setPersistentCache($entities);

    if (!$this->entityType->isPersistentlyCacheable()) {
      return;
    }

    // Create a separate cache entry that uses developer id in the cache id
    // instead of the email address. This way we can load a developer from
    // cache by using both ids.
    foreach ($entities as $id => $entity) {
      /** @var \Drupal\apigee_edge\Entity\Developer $entity */
      $this->cacheBackend->set($this->buildCacheId($entity->getDeveloperId()), $entity, $this->getPersistentCacheExpiration(), $this->getPersistentCacheTags($entity));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(array $ids = NULL) {
    parent::resetCache($ids);

    // Ensure that if ids contains email addresses we also invalidate cache
    // entries that refers to the same entities by developer id and vice-versa.
    // See getPersistentCacheTags() for more insight.
    if ($ids && $this->entityType->isPersistentlyCacheable()) {
      $cids = [];
      foreach ($ids as $id) {
        $cids[] = "{$this->entityTypeId}:{$id}:values";
      }
      Cache::invalidateTags([$this->entityTypeId . ':values']);
    }
  }

}
