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

use Apigee\Edge\Api\Management\Entity\AppInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Egulias\EmailValidator\EmailValidatorInterface;

/**
 * Extended app cache implementation for the developer app controller.
 *
 * It creates extra owner cache entries if the developer app controller got
 * initialized by using a developer's email address an not its developer id
 * (uuid). App cache can only create owner cache entries by using developer id
 * because this is what is available on apps.
 */
final class DeveloperAppCache extends AppCache implements DeveloperAppCacheInterface {

  /**
   * Email address or id (uuid) of a developer.
   *
   * @var string
   */
  protected $owner;

  /**
   * The email validator service.
   *
   * @var \Egulias\EmailValidator\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * DeveloperAppCache constructor.
   *
   * @param string $owner
   *   Developer email address or id (uuid).
   * @param \Egulias\EmailValidator\EmailValidatorInterface $email_validator
   *   The email validator service.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $cache
   *   The memory cache backend used by the app cache.
   */
  public function __construct(string $owner, EmailValidatorInterface $email_validator, MemoryCacheInterface $cache) {
    parent::__construct($cache);
    $this->owner = $owner;
    $this->emailValidator = $email_validator;
  }

  /**
   * {@inheritdoc}
   */
  public function saveAppsToCache(array $apps): void {
    parent::saveAppsToCache($apps);
    if ($this->emailValidator->isValid($this->owner)) {
      // Create cache entries for apps by using developer's email address
      // as well.
      $owner_app_id_cache_items = [];
      /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface $app */
      foreach ($apps as $app) {
        // Store app name => app id (uuid) mapping in owner's cache.
        $owner_cid = $this->appOwnerCacheId($this->owner);
        // One-time owner cache item setup.
        if (!array_key_exists($owner_cid, $owner_app_id_cache_items)) {
          $owner_cache = $this->getAppIdsCacheByOwner($this->owner);
          // Owner's app name => app id cache is empty.
          if ($owner_cache === NULL) {
            // Tag the cache with the owner (developer id or company name) for
            // easier invalidation.
            $owner_app_id_cache_items[$owner_cid]['tags'] = [$this->appOwnerCacheId($this->owner)];
          }
          else {
            // Keep the previously set values in owner's cache. Do not override
            // them!
            $owner_app_id_cache_items[$owner_cid]['data'] = $owner_cache->data;
            $owner_app_id_cache_items[$owner_cid]['tags'] = $owner_cache->tags;
          }
        }
        // Store app name => app id (uuid) mapping in owner's cache.
        $owner_app_id_cache_items[$owner_cid]['data'][$app->getName()] = $app->getAppId();
      }
      $this->cacheBackend->setMultiple($owner_app_id_cache_items);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeAppFromCache(AppInterface $app): void {
    parent::removeAppFromCache($app);
    if ($this->emailValidator->isValid($this->owner)) {
      $this->removeAppIdsFromCacheByOwner($this->owner, [$app->getName()]);
    }
  }

}
