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

namespace Drupal\apigee_edge\Entity\Storage;

use Drupal\apigee_edge\Entity\AppInterface;
use Drupal\apigee_edge\Entity\Controller\AppControllerInterface;
use Drupal\apigee_edge\Entity\Controller\EntityCacheAwareControllerInterface;
use Drupal\apigee_edge\Entity\FieldableEdgeEntityInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base entity storage class for developer and team (company) app entities.
 *
 * @internal
 */
abstract class AppStorage extends AttributesAwareFieldableEdgeEntityStorageBase {

  /**
   * The app controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\AppControllerInterface
   */
  protected $appController;

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
   * @param \Drupal\apigee_edge\Entity\Controller\AppControllerInterface $app_controller
   *   The app controller service.
   */
  public function __construct(EntityTypeInterface $entity_type, CacheBackendInterface $cache_backend, MemoryCacheInterface $memory_cache, TimeInterface $system_time, AppControllerInterface $app_controller) {
    parent::__construct($entity_type, $cache_backend, $memory_cache, $system_time);
    $this->appController = $app_controller;
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
      $container->get('apigee_edge.controller.app')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function loadUnchanged($id) {
    // Clear the app controller's cache if it has one.
    if ($this->appController instanceof EntityCacheAwareControllerInterface) {
      // Id could be an UUID or an app name.
      // We do not know who is the owner so we have need the app object to be
      // invalidate the app cache entry by the app id (UUID).
      /** @var \Apigee\Edge\Api\Management\Entity\AppInterface $entity */
      $entity = $this->entityController()->load($id);
      $this->appController->entityCache()->removeEntities([$entity->getAppId()]);
    }
    return parent::loadUnchanged($id);
  }

  /**
   * {@inheritdoc}
   */
  protected function initFieldValues(FieldableEdgeEntityInterface $entity, array $values = [], array $field_names = []) {
    // Initialize display name and description field's value from the display
    // name attribute if needed.
    // @see \Apigee\Edge\Api\Management\Entity\App::getDisplayName()
    if (!array_key_exists('displayName', $values) && array_key_exists('attributes', $values) && $values['attributes']->has('DisplayName')) {
      $values['displayName'] = $values['attributes']->getValue('DisplayName');
    }
    // @see \Apigee\Edge\Api\Management\Entity\App::getDescription()
    if (!array_key_exists('description', $values) && array_key_exists('attributes', $values) && $values['attributes']->has('Notes')) {
      $values['description'] = $values['attributes']->getValue('Notes');
    }
    parent::initFieldValues($entity, $values, $field_names);
  }

  /**
   * {@inheritdoc}
   */
  protected function getFromStorage(array $ids = NULL) {
    // Try to load entities from the entity controller's static cache.
    if (!empty($ids)) {
      // If $ids are developer app ids (UUIDs) let's check whether all (SDK)
      // entities can be served from the shared app (controller) cache.
      // When AppQueryBase::getFromStorage() tries to reduce the API calls by
      // doing something smart it could happen that entity storage's static
      // cache has not warmed up yet but the shared app cache did.
      // @see \Drupal\apigee_edge\Entity\Query\AppQueryBase::getFromStorage()
      if ($this->appController instanceof EntityCacheAwareControllerInterface) {
        $cached_entities = $this->appController->entityCache()->getEntities($ids);
        if (count($cached_entities) === count($ids)) {
          return $this->processLoadedEntities($ids, $cached_entities);
        }
      }
    }
    return parent::getFromStorage($ids);
  }

  /**
   * {@inheritdoc}
   */
  final protected function getPersistentCacheTags(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\AppInterface $entity */
    $cache_tags = parent::getPersistentCacheTags($entity);
    return array_merge($cache_tags, $this->getCacheTagsByOwner($entity));
  }

  /**
   * Generates cache tags for an app.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   App entity.
   *
   * @return array
   *   Array of cache tags.
   */
  private function getPersistentCacheTagsForAppName(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\AppInterface $entity */
    $cache_tags = array_merge([
      "{$this->entityTypeId}",
      "{$this->entityTypeId}:app_names",
      "{$this->entityTypeId}:{$entity->id()}",
      "{$this->entityTypeId}:{$entity->id()}:app_name",
    ], $this->getCacheTagsByOwner($entity));

    return $cache_tags;
  }

  /**
   * Returns app owner related cache tags for an app.
   *
   * These cache tags gets added to the generated app cache entry which ensures
   * when app's owner gets deleted the related app cache entries gets
   * invalidated as well.
   *
   * @param \Drupal\apigee_edge\Entity\AppInterface $app
   *   The app entity.
   *
   * @return array
   *   Array of app owner related cache entries.
   *
   * @see getPersistentCacheTags()
   * @see getPersistentCacheTagsForAppName()
   */
  abstract protected function getCacheTagsByOwner(AppInterface $app): array;

  /**
   * {@inheritdoc}
   */
  public function resetCache(array $ids = NULL) {
    parent::resetCache($ids);
    if ($this->entityType->isStaticallyCacheable() && $ids) {
      $tags = [];
      foreach ($ids as $id) {
        $tags[] = "{$this->entityTypeId}:{$id}:app_name";
      }
      if ($this->entityType->isPersistentlyCacheable()) {
        Cache::invalidateTags($tags);
      }
    }
    else {
      if ($this->entityType->isPersistentlyCacheable()) {
        Cache::invalidateTags([$this->entityTypeId . ':app_names']);
      }
    }
    // We do not reset the app cache because app controllers handles the
    // cache invalidation.
    // We tried to call it once here, but then we had some trouble with app
    // creation. After an app has been created in doSave() doPostSave() called
    // this method. Because we cleared to controller's app cache the
    // DeveloperAppCreateForm::save() could not load the credential form the
    // app. (Of course, we do not want to re-load the app just because of this.)
    // @see \Drupal\apigee_edge\Entity\Form\DeveloperAppCreateForm::save()
  }

  /**
   * {@inheritdoc}
   */
  protected function setPersistentCache(array $entities) {
    parent::setPersistentCache($entities);

    if (!$this->entityType->isPersistentlyCacheable()) {
      return;
    }

    /** @var \Drupal\apigee_edge\Entity\AppInterface $entity */
    foreach ($entities as $entity) {
      // Create an additional cache entry for each app that stores the app id
      // for each developerId or company (team) name + app name combinations.
      // Thanks for this we can run queries faster that tries to an load app
      // by using these two properties instead of the app id.
      $this->cacheBackend->set($this->buildCacheIdForAppName($entity->getAppOwner(), $entity->getName()), $entity->getAppId(), $this->getPersistentCacheExpiration(), $this->getPersistentCacheTagsForAppName($entity));
    }
  }

  /**
   * Generates a unique cache id for app name.
   *
   * Developer id (uuid)/company name + app name together also represent a
   * unique app entity id.
   *
   * @param string $owner
   *   Developer id (UUID) or team (company) name.
   * @param string $app_name
   *   The name of an app.
   *
   * @return string
   *   Unique cache cid.
   */
  protected function buildCacheIdForAppName(string $owner, string $app_name) {
    // We do not need to worry about the length of the cid because the cache
    // backend should ensure that the length of the cid is not too long.
    // @see \Drupal\Core\Cache\DatabaseBackend::normalizeCid()
    return "app_names:{$this->entityTypeId}:{$owner}:{$app_name}";
  }

  /**
   * Returns cached app id for developer id/company name + app name.
   *
   * @param string $owner
   *   UUID of a developer or a team (company) name.
   * @param string $app_name
   *   Name of an app owned by the provided owner.
   *
   * @return null|string
   *   The app id if it found, null otherwise.
   */
  public function getCachedAppId(string $owner, string $app_name) {
    $item = $this->cacheBackend->get($this->buildCacheIdForAppName($owner, $app_name));
    return $item ? $item->data : NULL;
  }

}
