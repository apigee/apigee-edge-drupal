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

namespace Drupal\apigee_edge_teams;

use Apigee\Edge\Api\Management\Structure\CompanyMembership;
use Drupal\apigee_edge\MemoryCacheFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Providers a persistent & non-persistent cache for company membership objects.
 *
 * @internal You should use the team membership manager service instead of this.
 */
final class CompanyMembershipObjectCache implements CompanyMembershipObjectCacheInterface {

  /**
   * The default persistent cache bin used by this service.
   *
   * @var string
   */
  public const DEFAULT_CACHE_BIN = 'apigee_edge_teams_company_membership_object';

  /**
   * Persistent cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private $persistentCacheBackend;

  /**
   * Number of seconds until an entity can be served from cache.
   *
   * -1 is also an allowed, which means the item should never be removed unless
   * explicitly deleted.
   *
   * @var int
   */
  private $persistentCacheExpiration = CacheBackendInterface::CACHE_PERMANENT;

  /**
   * Non-persistent cache backend.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  private $memoryCache;

  /**
   * The system time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private $systemTime;

  /**
   * CompanyMembershipObjectCache constructor.
   *
   * @param \Drupal\Core\Cache\CacheFactoryInterface $cache_factory
   *   The cache factory.
   * @param \Drupal\apigee_edge\MemoryCacheFactoryInterface $memory_cache_factory
   *   The module specific memory cache factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The system time service.
   */
  public function __construct(CacheFactoryInterface $cache_factory, MemoryCacheFactoryInterface $memory_cache_factory, ConfigFactoryInterface $config, TimeInterface $time) {
    $this->persistentCacheBackend = $cache_factory->get(self::DEFAULT_CACHE_BIN);
    // TODO Should we introduce dedicated cache expiration configuration for
    // this?
    $this->persistentCacheExpiration = $config->get('apigee_edge_teams.team_settings')->get('cache_expiration');
    $this->memoryCache = $memory_cache_factory->get('company_membership_object');
    $this->systemTime = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function saveMembership(string $company, CompanyMembership $membership): void {
    // Tag company membership cache entries with members' (developers') email
    // addresses for easier cache invalidation when a developer gets removed.
    $tags = [];
    foreach (array_keys($membership->getMembers()) as $developer_email) {
      $tags[] = "developer:{$developer_email}";
    }
    $this->memoryCache->set($company, $membership, CacheBackendInterface::CACHE_PERMANENT, $tags);
    $expiration = $this->persistentCacheExpiration;
    if ($expiration !== CacheBackendInterface::CACHE_PERMANENT) {
      $expiration = $this->systemTime->getCurrentTime() + $expiration;
    }
    $this->persistentCacheBackend->set($company, $membership, $expiration, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function removeMembership(string $company): void {
    $this->memoryCache->invalidate($company);
    $this->persistentCacheBackend->invalidate($company);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMemberships(array $tags): void {
    $this->memoryCache->invalidateTags($tags);
    if ($this->persistentCacheBackend instanceof CacheTagsInvalidatorInterface) {
      $this->persistentCacheBackend->invalidateTags($tags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMembership(string $company): ?CompanyMembership {
    if ($data = $this->memoryCache->get($company)) {
      return $data->data;
    }

    if ($data = $this->persistentCacheBackend->get($company)) {
      // Next time return this from the memory cache.
      $this->memoryCache->set($company, $data->data);
      return $data->data;
    }

    return NULL;
  }

}
