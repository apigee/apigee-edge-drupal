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

use Egulias\EmailValidator\EmailValidatorInterface;

/**
 * Developer specific app cache by app owner factory service.
 */
final class DeveloperAppCacheFactory implements AppCacheByOwnerFactoryInterface {

  /**
   * Internal cache for created instances.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\DeveloperAppCache[]
   */
  private $instances = [];

  /**
   * The app cache service that stores app by their app id (UUID).
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface
   */
  private $appCache;

  /**
   * The (general) app cache by owner factory.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\GeneralAppCacheByAppOwnerFactoryInterface
   */
  private $appCacheByOwnerFactory;

  /**
   * The developer cache service.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\DeveloperCache
   */
  private $developerCache;

  /**
   * The email validator service.
   *
   * @var \Egulias\EmailValidator\EmailValidatorInterface
   */
  private $emailValidator;

  /**
   * DeveloperAppCacheFactory constructor.
   *
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheInterface $app_cache
   *   The app cache service that stores app by their app id (UUID).
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\GeneralAppCacheByAppOwnerFactoryInterface $app_cache_by_owner_factory
   *   The (general) app cache by owner factory.
   * @param \Drupal\apigee_edge\Entity\Controller\Cache\DeveloperCache $developer_cache
   *   The developer app cache service.
   * @param \Egulias\EmailValidator\EmailValidatorInterface $email_validator
   *   The email validator service.
   */
  public function __construct(AppCacheInterface $app_cache, GeneralAppCacheByAppOwnerFactoryInterface $app_cache_by_owner_factory, DeveloperCache $developer_cache, EmailValidatorInterface $email_validator) {
    $this->appCache = $app_cache;
    $this->appCacheByOwnerFactory = $app_cache_by_owner_factory;
    $this->developerCache = $developer_cache;
    $this->emailValidator = $email_validator;
  }

  /**
   * Returns the same app cache instance for an owner.
   *
   * @param string $owner
   *   Developer id (UUID) or email.
   *
   * @return \Drupal\apigee_edge\Entity\Controller\Cache\AppCacheByAppOwnerInterface
   *   The developer app cache that belongs to this owner.
   */
  public function getAppCache(string $owner): AppCacheByAppOwnerInterface {
    if (!isset($this->instances[$owner])) {
      $this->instances[$owner] = new DeveloperAppCache($owner, $this->appCache, $this->appCacheByOwnerFactory, $this->developerCache, $this->emailValidator);
    }

    return $this->instances[$owner];
  }

}
