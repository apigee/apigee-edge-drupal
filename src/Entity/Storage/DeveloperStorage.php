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
use Drupal\apigee_edge\Entity\DrupalEntityFactory;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
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
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Configuration factory.
   * @param \Drupal\Component\Datetime\TimeInterface $systemTime
   *   System time.
   */
  public function __construct(SDKConnectorInterface $sdkConnector, EntityTypeInterface $entity_type, CacheBackendInterface $cache, LoggerInterface $logger, Connection $database, ConfigFactoryInterface $config, TimeInterface $systemTime) {
    parent::__construct($sdkConnector, $entity_type, $cache, $logger, $systemTime);
    $this->database = $database;
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
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getController(SDKConnectorInterface $connector): EntityCrudOperationsControllerInterface {
    return new DeveloperController($connector->getOrganization(), $connector->getClient(), new DrupalEntityFactory());
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
    });

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * Adds Drupal user information to loaded entities.
   */
  protected function postLoad(array &$entities) {
    parent::postLoad($entities);
    $developerId_mail_map = [];
    /** @var \Drupal\apigee_edge\Entity\Developer $entity */
    foreach ($entities as $entity) {
      $developerId_mail_map[$entity->getDeveloperId()] = $entity->getEmail();
    }

    $query = $this->database->select('users_field_data', 'ufd');
    $query->fields('ufd', ['mail', 'uid'])
      ->condition('mail', $developerId_mail_map, 'IN');
    $mail_uid_map = $query->execute()->fetchAllKeyed();

    foreach ($entities as $entity) {
      // If developer id is not in this map it means the developer does
      // not exist in Drupal yet (developer syncing between Edge and Drupal is
      // required) or the developer id has not been stored in
      // related Drupal user yet.
      // This can be fixed with running developer sync too,
      // because it could happen that the user had been
      // created in Drupal before Edge connected was configured.
      // Although, this could be a result of a previous error
      // but there should be a log about that.
      if (isset($mail_uid_map[$developerId_mail_map[$entity->getDeveloperId()]])) {
        $entity->setOwnerId($mail_uid_map[$developerId_mail_map[$entity->getDeveloperId()]]);
      }
    }
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
