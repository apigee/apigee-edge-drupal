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
use Drupal\apigee_edge\Entity\AppCredentialStorageAwareTrait;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppController;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Egulias\EmailValidator\EmailValidatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller class for developer apps.
 */
class DeveloperAppStorage extends FieldableEdgeEntityStorageBase implements DeveloperAppStorageInterface {

  use AppCredentialStorageAwareTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The email validator.
   *
   * @var \Egulias\EmailValidator\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * Constructs an DeveloperAppStorage instance.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $sdkConnector
   *   The SDK connector service.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to be used.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger to be used.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Configuration factory.
   * @param \Drupal\Component\Datetime\TimeInterface $systemTime
   *   System time.
   * @param \Egulias\EmailValidator\EmailValidatorInterface $emailValidator
   *   Email validator.
   */
  public function __construct(SDKConnectorInterface $sdkConnector, EntityTypeInterface $entity_type, CacheBackendInterface $cache, LoggerInterface $logger, EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $config, TimeInterface $systemTime, EmailValidatorInterface $emailValidator) {
    parent::__construct($sdkConnector, $entity_type, $cache, $logger, $systemTime);
    $this->entityTypeManager = $entityTypeManager;
    $this->cacheExpiration = $config->get('apigee_edge.common_app_settings')->get('cache_expiration');
    $this->emailValidator = $emailValidator;
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
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('datetime.time'),
      $container->get('email.validator')
    );
  }

  /**
   * Gets a DeveloperAppController instance.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK Connector service.
   *
   * @return \Apigee\Edge\Controller\EntityCrudOperationsControllerInterface
   *   The DeveloperAppController instance.
   *
   * @method listByDeveloper
   */
  public function getController(SDKConnectorInterface $connector): EntityCrudOperationsControllerInterface {
    return new DeveloperAppController($connector->getOrganization(), $connector->getClient());
  }

  /**
   * {@inheritdoc}
   */
  protected function initFieldValues(FieldableEntityInterface $entity, array $values = [], array $field_names = []) {
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
  public function loadByDeveloper(string $developerId): array {
    $query = $this->getQuery();
    // We have to figure out whether this is an email or a UUID.
    if ($this->emailValidator->isValid($developerId)) {
      $query->condition('email', $developerId);
    }
    else {
      $query->condition('developerId', $developerId);
    }
    $ids = $query->execute();
    return $this->loadMultiple(array_values($ids));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function doDelete($entities) {
    parent::doDelete($entities);
    // We remove app credential from user's private app credential storage
    // here instead of in resetCache(), because there we would only have
    // appId-s but what we need is appName + developerId for this.
    // Also, most cache invalidation happens in the controller level, but
    // we do this here for the above discussed reasons.
    // @see \Drupal\apigee_edge\Entity\Controller\DeveloperAppController
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    foreach ($entities as $entity) {
      $this->clearAppCredentialsFromStorage($entity->getDeveloperId(), $entity->getName());
    }
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
   * @param string $developerId
   *   The uuid of a developer.
   * @param string $appName
   *   The name of a developer app.
   *
   * @return string
   *   Unique cache cid.
   */
  private function buildCacheIdForAppName(string $developerId, string $appName) {
    // We do not need to worry about the length of the cid because the cache
    // backend should ensure that the length of the cid is not too long.
    // @see \Drupal\Core\Cache\DatabaseBackend::normalizeCid()
    return "app_names:{$this->entityTypeId}:{$developerId}:{$appName}";
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(array $ids = NULL) {
    parent::resetCache($ids);
    if ($ids) {
      $tags = [];
      foreach ($ids as $id) {
        unset($this->entities[$id]);
        $tags[] = "{$this->entityTypeId}:{$id}:app_name";
      }
      if ($this->entityType->isPersistentlyCacheable()) {
        Cache::invalidateTags($tags);
      }
    }
    else {
      $this->entities = [];
      if ($this->entityType->isPersistentlyCacheable()) {
        Cache::invalidateTags([$this->entityTypeId . ':app_names']);
      }
    }
  }

  /**
   * Returns cached app id for a developerId and app name.
   *
   * @param string $developerId
   *   UUID of a developer.
   * @param string $appName
   *   Name of an app owner by the provided developerId.
   *
   * @return null|string
   *   The app id if it found, null otherwise.
   */
  public function getCachedAppId(string $developerId, string $appName) {
    $item = $this->cacheBackend->get($this->buildCacheIdForAppName($developerId, $appName));
    return $item ? $item->data : NULL;
  }

}
