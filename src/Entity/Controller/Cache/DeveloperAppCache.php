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

namespace Drupal\apigee_edge\Entity\Controller\Cache;

use Apigee\Edge\Entity\EntityInterface;
use Egulias\EmailValidator\EmailValidatorInterface;

/**
 * Default cache store for developer apps.
 *
 * Developer apps can be loaded by used their owner's email address or
 * developer id (UUID). This cache tries to reduce the API calls by registering
 * loaded apps for both owner ids and falling back to the developer id when
 * $owner contains an email.
 *
 * @internal Do not create an instance from this directly. Always use the
 * factory.
 */
final class DeveloperAppCache implements AppCacheByAppOwnerInterface {

  /**
   * The (general) app cache by app owner factory service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\GeneralAppCacheByAppOwnerFactoryInterface
   */
  private $appCacheByOwnerFactory;

  /**
   * The email validator service.
   *
   * @var \Egulias\EmailValidator\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * The app cache service that stores app by their app id (UUID).
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface
   */
  private $appCache;

  /**
   * Developer id (uuid) or email address.
   *
   * @var string
   */
  private $owner;

  /**
   * The default app cache by owner that belongs to the $owner.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByAppOwnerInterface
   */
  private $defaultAppCacheByOwner;

  /**
   * The developer cache service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\DeveloperCache
   */
  private $developerCache;

  /**
   * Indicates whether all entities in the cache or not.
   *
   * @var bool
   */
  private $allEntitiesInCache = FALSE;

  /**
   * DeveloperAppCache constructor.
   *
   * @param string $owner
   *   Developer id (UUID), email address.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache
   *   The app cache service that stores app by their app id (UUID).
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\GeneralAppCacheByAppOwnerFactoryInterface $app_cache_by_owner_factory
   *   The (general) app cache by owner service.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\DeveloperCache $developer_cache
   *   The developer cache service.
   * @param \Egulias\EmailValidator\EmailValidatorInterface $email_validator
   *   The email validator service.
   */
  public function __construct(string $owner, AppCacheInterface $app_cache, GeneralAppCacheByAppOwnerFactoryInterface $app_cache_by_owner_factory, DeveloperCache $developer_cache, EmailValidatorInterface $email_validator) {
    $this->appCache = $app_cache;
    $this->owner = $owner;
    $this->appCacheByOwnerFactory = $app_cache_by_owner_factory;
    $this->emailValidator = $email_validator;
    $this->defaultAppCacheByOwner = $app_cache_by_owner_factory->getAppCache($owner);
    $this->developerCache = $developer_cache;
  }

  /**
   * {@inheritdoc}
   */
  public function saveEntities(array $entities): void {
    $this->defaultAppCacheByOwner->saveEntities($entities);
    $this->saveAppsToAppByOwnerCacheByDeveloperId($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function removeEntities(array $ids): void {
    $apps_by_owner_cache_by_developer_id = $this->getAppsByOwnerCacheByDeveloperId();
    // If $this->owner contains a developer email and we could not load its
    // apps by owner cache by developer id (UUID) above, let's try to load it
    // by using the developer id from a cached developer app.
    if ($apps_by_owner_cache_by_developer_id === NULL) {
      $entities = $this->getEntities();
      if (empty($entities)) {
        // If there were no apps in the cache then there is nothing to do.
        // No matter what is the content of $ids, apps with those ids
        // (app names) could not exist in cache.
        return;
      }
      /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface $app */
      $app = reset($entities);
      $apps_by_owner_cache_by_developer_id = $this->appCacheByOwnerFactory->getAppCache($app->getDeveloperId());
    }

    $apps_by_owner_cache_by_developer_id->removeEntities($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(array $ids = []): array {
    // If this method returns an empty array the developer app controller
    // loads the app(s) from Apigee Edge.
    $entities = [];

    $apps_by_owner_cache_by_developer_id = $this->getAppsByOwnerCacheByDeveloperId();
    if ($apps_by_owner_cache_by_developer_id) {
      $entities = $apps_by_owner_cache_by_developer_id->getEntities($ids);
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(string $id): ?EntityInterface {
    $entities = $this->getEntities([$id]);
    return $entities ? reset($entities) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function allEntitiesInCache(bool $all_entities_in_cache): void {
    $this->allEntitiesInCache = $all_entities_in_cache;
  }

  /**
   * {@inheritdoc}
   */
  public function isAllEntitiesInCache(): bool {
    return $this->allEntitiesInCache;
  }

  /**
   * Saves apps to the app by owner cache by developer id.
   *
   * Ensures that even if $this->owner is an email the cached apps gets
   * registered under the developer's developer id (UUID) as well in the
   * app by owner cache.
   *
   * @param \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface[] $entities
   *   Array of developer apps.
   */
  private function saveAppsToAppByOwnerCacheByDeveloperId(array $entities): void {
    if (!empty($entities) && $this->emailValidator->isValid($this->owner)) {
      $entity = reset($entities);
      $app_by_owner_cache_by_developer_id = $this->appCacheByOwnerFactory->getAppCache($entity->getDeveloperId());
      $app_by_owner_cache_by_developer_id->saveEntities([$entity]);
    }
  }

  /**
   * Returns the apps by owner cache that belongs to the a developer by UUID.
   *
   * If $this->owner is an email, this method tries to return the app by owner
   * cache that belongs to the same developer by its UUID. For this, it tries
   * to load the developer entity from the developer cache by email. If the
   * developer could not be found by email then it does not load the developer
   * from Apigee Edge because that could increase the amount of API calls and
   * what we try to achieve here is its opposite.
   *
   * @return \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByAppOwnerInterface|null
   *   The app cache by owner instance that belongs to the developer by its
   *   developer id (UUID), or null if developer's UUID could not be determined
   *   without loading the developer from Apigee Edge by email.
   */
  private function getAppsByOwnerCacheByDeveloperId(): ?AppCacheByAppOwnerInterface {
    if ($this->emailValidator->isValid($this->owner)) {
      /** @var \Apigee\Edge\Api\Management\Entity\DeveloperInterface|null $developer */
      $developer = $this->developerCache->getEntity($this->owner);
      if ($developer) {
        return $this->appCacheByOwnerFactory->getAppCache($developer->getDeveloperId());
      }

      return NULL;
    }
    else {
      return $this->defaultAppCacheByOwner;
    }
  }

}
