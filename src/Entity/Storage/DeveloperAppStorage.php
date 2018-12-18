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

use Drupal\apigee_edge\Entity\Controller\AppControllerInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppControllerFactoryInterface;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppEdgeEntityControllerProxy;
use Drupal\apigee_edge\Entity\Controller\EdgeEntityControllerInterface;
use Drupal\apigee_edge\Entity\Controller\EntityCacheAwareControllerInterface;
use Drupal\apigee_edge\Entity\FieldableEdgeEntityInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Egulias\EmailValidator\EmailValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity storage class for Developer app entities.
 */
class DeveloperAppStorage extends AttributesAwareFieldableEdgeEntityStorageBase implements DeveloperAppStorageInterface {

  /**
   * The app entity controller for unified CRUDL operations.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\EdgeEntityControllerInterface
   */
  private $appEntityController;

  /**
   * The developer app controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppControllerFactoryInterface
   */
  private $developerAppControllerFactory;

  /**
   * The app controller service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\AppControllerInterface
   */
  private $appController;

  /**
   * The email validator service.
   *
   * @var \Egulias\EmailValidator\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * DeveloperAppStorage constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to be used.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache.
   * @param \Drupal\Component\Datetime\TimeInterface $system_time
   *   The system time.
   * @param \Drupal\apigee_edge\Entity\Controller\DeveloperAppControllerFactoryInterface $developer_app_controller_factory
   *   The developer app controller factory service.
   * @param \Drupal\apigee_edge\Entity\Controller\AppControllerInterface $app_controller
   *   The app controller service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Configuration factory.
   * @param \Egulias\EmailValidator\EmailValidatorInterface $email_validator
   *   The email validator service.
   */
  public function __construct(EntityTypeInterface $entity_type, CacheBackendInterface $cache_backend, MemoryCacheInterface $memory_cache, TimeInterface $system_time, DeveloperAppControllerFactoryInterface $developer_app_controller_factory, AppControllerInterface $app_controller, ConfigFactoryInterface $config, EmailValidatorInterface $email_validator) {
    parent::__construct($entity_type, $cache_backend, $memory_cache, $system_time);
    $this->developerAppControllerFactory = $developer_app_controller_factory;
    $this->appController = $app_controller;
    $this->appEntityController = new DeveloperAppEdgeEntityControllerProxy($developer_app_controller_factory, $app_controller);
    $this->cacheExpiration = $config->get('apigee_edge.developer_app_settings')->get('cache_expiration');
    $this->emailValidator = $email_validator;
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
      $container->get('apigee_edge.controller.developer_app_controller_factory'),
      $container->get('apigee_edge.controller.app'),
      $container->get('config.factory'),
      $container->get('email.validator')
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
  protected function entityController(): EdgeEntityControllerInterface {
    return $this->appEntityController;
  }

  /**
   * {@inheritdoc}
   */
  public function loadByDeveloper(string $developer_id): array {
    $query = $this->getQuery();
    // We have to figure out whether this is an email or a UUID to call the
    // best API endpoint that is possible.
    if ($this->emailValidator->isValid($developer_id)) {
      $query->condition('email', $developer_id);
    }
    else {
      $query->condition('developerId', $developer_id);
    }
    $ids = $query->execute();
    return $this->loadMultiple(array_values($ids));
  }

  /**
   * {@inheritdoc}
   */
  protected function getPersistentCacheTags(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $cacheTags = parent::getPersistentCacheTags($entity);
    // Add Drupal user id to ensure that when the owner of the app (Drupal user)
    // is deleted then the cached developer app data is also purged.
    // (This also invalidates cached app data when a user is updated which
    // might be even good for us. Create a custom solution if this default
    // behavior becomes a bottleneck.)
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

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    foreach ($entities as $id => $entity) {
      // Create an additional cache entry for each app that stores the app id
      // for each developerId + app name combination.
      // Thanks for this we can run queries faster that tries to an load app
      // by using these two properties instead of the app id.
      $this->cacheBackend->set($this->buildCacheIdForAppName($entity->getDeveloperId(), $entity->getName()), $entity->getAppId(), $this->getPersistentCacheExpiration(), $this->getPersistentCacheTagsForAppName($entity));
    }
  }

  /**
   * Generates cache tags for an app.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Developer app entity.
   *
   * @return array
   *   Array of cache tags.
   */
  protected function getPersistentCacheTagsForAppName(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $cacheTags = [
      "{$this->entityTypeId}",
      "{$this->entityTypeId}:app_names",
      "{$this->entityTypeId}:{$entity->id()}",
      "{$this->entityTypeId}:{$entity->id()}:app_name",
    ];
    // Add Drupal user id to ensure that when the owner of the app (Drupal user)
    // is deleted then the cached developer app data is also purged.
    // (This also invalidates cached app data when a user is updated which
    // might be even good for us. Create a custom solution if this default
    // behavior becomes a bottleneck.)
    if ($entity->getOwnerId()) {
      $cacheTags[] = "user:{$entity->getOwnerId()}";
    }
    return $cacheTags;
  }

  /**
   * Generates a unique cache id for app name.
   *
   * Developer Id and app name together also represents a unique developer app
   * entity.
   *
   * @param string $developer_id
   *   The uuid of a developer.
   * @param string $app_name
   *   The name of a developer app.
   *
   * @return string
   *   Unique cache cid.
   */
  private function buildCacheIdForAppName(string $developer_id, string $app_name) {
    // We do not need to worry about the length of the cid because the cache
    // backend should ensure that the length of the cid is not too long.
    // @see \Drupal\Core\Cache\DatabaseBackend::normalizeCid()
    return "app_names:{$this->entityTypeId}:{$developer_id}:{$app_name}";
  }

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
   * Returns cached app id for a developerId and app name.
   *
   * @param string $developer_id
   *   UUID of a developer.
   * @param string $app_name
   *   Name of an app owner by the provided developerId.
   *
   * @return null|string
   *   The app id if it found, null otherwise.
   */
  public function getCachedAppId(string $developer_id, string $app_name) {
    $item = $this->cacheBackend->get($this->buildCacheIdForAppName($developer_id, $app_name));
    return $item ? $item->data : NULL;
  }

}
