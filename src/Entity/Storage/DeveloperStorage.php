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
use Apigee\Edge\Exception\ApiException;
use Drupal\apigee_edge\Entity\Controller\CachedManagementApiEdgeEntityControllerProxy;
use Drupal\apigee_edge\Entity\Controller\EdgeEntityControllerInterface;
use Drupal\apigee_edge\Entity\Controller\EntityCacheAwareControllerInterface;
use Drupal\apigee_edge\Entity\Controller\ManagementApiEdgeEntityControllerProxy;
use Drupal\apigee_edge\Entity\DeveloperCompaniesCacheInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity storage implementation for developers.
 */
class DeveloperStorage extends EdgeEntityStorageBase implements DeveloperStorageInterface {

  /**
   * The developer controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperControllerInterface
   */
  private $developerController;

  /**
   * Developer company membership cache.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperCompaniesCacheInterface
   */
  private $developerCompanies;

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
   * @param \Apigee\Edge\Api\Management\Controller\DeveloperControllerInterface $developer_controller
   *   The developer controller service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Configuration factory.
   * @param \Drupal\apigee_edge\Entity\DeveloperCompaniesCacheInterface $developer_companies_cache
   *   Developer company membership cache.
   */
  public function __construct(EntityTypeInterface $entity_type, CacheBackendInterface $cache_backend, MemoryCacheInterface $memory_cache, TimeInterface $system_time, DeveloperControllerInterface $developer_controller, ConfigFactoryInterface $config, DeveloperCompaniesCacheInterface $developer_companies_cache) {
    parent::__construct($entity_type, $cache_backend, $memory_cache, $system_time);
    $this->cacheExpiration = $config->get('apigee_edge.developer_settings')->get('cache_expiration');
    $this->developerController = $developer_controller;
    $this->developerCompanies = $developer_companies_cache;
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
      $container->get('apigee_edge.controller.developer'),
      $container->get('config.factory'),
      $container->get('apigee_edge.controller.cache.developer_companies')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function entityController(): EdgeEntityControllerInterface {
    if ($this->developerController instanceof EntityCacheAwareControllerInterface) {
      return new CachedManagementApiEdgeEntityControllerProxy($this->developerController);
    }
    return new ManagementApiEdgeEntityControllerProxy($this->developerController);
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
      $entities_by_developer_id = array_reduce($entities, function ($carry, $item) {
        // It could be an integer if developer UUID has been used as as an id
        // instead of the email address.
        if (is_object($item)) {
          /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $item */
          $carry[$item->getDeveloperId()] = $item;
        }
        return $carry;
      }, []);
      $entities = array_merge($entities, $entities_by_developer_id);
      $requested_entities = [];
      // Ensure that the returned array is ordered the same as the original
      // $ids array if this was passed in and remove any invalid ids.
      $passed_ids = array_flip(array_intersect_key(array_flip($ids), $entities));
      foreach ($passed_ids as $id) {
        $requested_entities[$id] = $entities[$id];
      }
      $entities = $requested_entities;
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $entity */
    $developer_status = $entity->getStatus();
    $result = parent::doSave($id, $entity);

    // In case of entity update, the original email must be
    // cleared before a new API call.
    if ($result === SAVED_UPDATED) {
      $entity->resetOriginalEmail();
    }
    // Change the status of the developer in Apigee Edge.
    // TODO Only change it if it has changed.
    try {
      $this->developerController->setStatus($entity->id(), $developer_status);
    }
    catch (ApiException $exception) {
      throw new EntityStorageException($exception->getMessage(), $exception->getCode(), $exception);
    }
    // Apply status change in the entity object as well.
    $entity->setStatus($developer_status);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPersistentCacheTags(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\Developer $entity */
    $cache_tags = parent::getPersistentCacheTags($entity);
    $cache_tags = $this->sanitizeCacheTags($entity->id(), $cache_tags);
    // Create tags by developerId (besides email address).
    $cache_tags[] = "{$this->entityTypeId}:{$entity->uuid()}";
    $cache_tags[] = "{$this->entityTypeId}:{$entity->uuid()}:values";
    // Also add a tag by developer's Drupal user id to ensure that cached
    // developer data is invalidated when the related Drupal user gets changed
    // or deleted.
    if ($entity->getOwnerId()) {
      $cache_tags[] = "user:{$entity->getOwnerId()}";
    }
    return $cache_tags;
  }

  /**
   * Sanitizes accented characters from a generated cache tag.
   *
   * If $id is an email address (which the default entity id of a developer
   * entity in Drupal) and it contains accented characters it must be sanitized
   * before they could be saved to the database backend.
   *
   * @param string $id
   *   Developer UUID or email address.
   * @param string $tag
   *   Generated cache tag that may or may not contains the id.
   *
   * @return string
   *   The sanitized cache tags.
   */
  private function sanitizeCacheTag(string $id, string $tag) : string {
    return str_replace($id, filter_var($id, FILTER_SANITIZE_ENCODED), $tag);
  }

  /**
   * Sanitizes accented characters from generated cache tags.
   *
   * @param string $id
   *   Developer UUID or email address.
   * @param array $cache_tags
   *   Array of generated cache tags.
   *
   * @return array
   *   Array of sanitized cache tags.
   *
   * @see sanitizeCacheTag()
   */
  private function sanitizeCacheTags(string $id, array $cache_tags): array {
    return array_map(function ($tag) use ($id) {
      return $this->sanitizeCacheTag($id, $tag);
    }, $cache_tags);
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
    foreach ($entities as $entity) {
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
      $tags = [];
      foreach ($ids as $id) {
        $tags[] = $this->sanitizeCacheTag($id, "{$this->entityTypeId}:{$id}");
        $tags[] = $this->sanitizeCacheTag($id, "{$this->entityTypeId}:{$id}:values");
      }
      Cache::invalidateTags($tags);
    }

    // Remove related entries in the developer company membership cache.
    if (empty($ids)) {
      $this->developerCompanies->remove();
    }
    else {
      // We can not be sure whether ids are developer ids (UUIDs) or email
      // addresses so we invalidate entries by tags.
      $this->developerCompanies->invalidate(array_map(function ($id) {
        return "developer:{$id}";
      }, $ids));
    }
  }

}
