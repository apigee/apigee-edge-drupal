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

namespace Drupal\Tests\apigee_edge\Kernel;

use Apigee\Edge\Api\Management\Entity\CompanyApp;
use Apigee\Edge\Api\Management\Entity\Developer;
use Apigee\Edge\Api\Management\Entity\DeveloperApp;
use Drupal\KernelTests\KernelTestBase;

/**
 * Apigee Edge entity memory cache tests.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class EntityMemoryCacheTest extends KernelTestBase {

  /**
   * The entity cache implementation to test.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\EntityCacheInterface
   */
  protected $entityCache;

  /**
   * The entity id cache implementation to test.
   *
   * @var \Drupal\apigee_edge\Entity\Controller\Cache\EntityIdCacheInterface
   */
  protected $entityIdCache;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'apigee_edge',
    'key',
  ];

  /**
   * Tests developer entity memory cache.
   */
  public function testDeveloperEntityMemoryCache() {
    $this->entityCache = $this->container->get('apigee_edge.controller.cache.developer');
    $this->entityIdCache = $this->container->get('apigee_edge.controller.cache.developer_ids');

    // Generate developer entities with random data.
    $developers = [];
    for ($i = 0; $i < 3; $i++) {
      $id = $this->getRandomUniqueId();
      $developers[$id] = new Developer([
        'developerId' => $id,
        'email' => strtolower($this->randomMachineName()) . '@example.com',
      ]);
    }

    $this->assertFullCaches($developers);

    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperInterface $developer */
    foreach ($developers as $developer) {
      // Load developer by email and by id.
      $this->assertSame($developer, $this->entityCache->getEntity($developer->getEmail()));
      $this->assertSame($developer, $this->entityCache->getEntity($developer->getDeveloperId()));
      $this->assertContains($developer->getEmail(), $this->entityIdCache->getIds());

      // Remove developer from cache.
      $this->entityCache->removeEntities([$developer->getDeveloperId(), $this->getRandomGenerator()->string()]);
      $this->assertNull($this->entityCache->getEntity($developer->getEmail()));
      $this->assertNull($this->entityCache->getEntity($developer->getDeveloperId()));
      $this->assertNotContains($developer->getEmail(), $this->entityIdCache->getIds());
    }

    $this->assertEmptyCaches();
  }

  /**
   * Tests app entity memory cache.
   */
  public function testAppEntityMemoryCache() {
    $this->entityCache = $this->container->get('apigee_edge.controller.cache.apps');
    $this->entityIdCache = $this->container->get('apigee_edge.controller.cache.app_ids');

    // Generate developer app entities with random data.
    $developer_apps = [];
    $parent_id = $this->getRandomUniqueId();
    for ($i = 0; $i < 2; $i++) {
      $id = $this->getRandomUniqueId();
      $developer_apps[$id] = new DeveloperApp([
        'appId' => $id,
        'name' => $this->getRandomUniqueId(),
        'developerId' => $parent_id,
      ]);
    }

    // Generate company app entities with random data.
    $company_apps = [];
    $parent_id = $this->getRandomUniqueId();
    for ($i = 0; $i < 2; $i++) {
      $id = $this->getRandomUniqueId();
      $company_apps[$id] = new CompanyApp([
        'appId' => $id,
        'name' => $this->getRandomUniqueId(),
        'companyName' => $parent_id,
      ]);
    }

    $apps = $developer_apps + $company_apps;

    $this->assertFullCaches($apps);

    /** @var \Apigee\Edge\Api\Management\Entity\AppInterface $app */
    foreach ($apps as $app) {
      // Load app by id and by owner.
      $this->assertSame($app, $this->entityCache->getEntity($app->getAppId()));
      $this->assertContains($app, $this->entityCache->getAppsByOwner($this->entityCache->getAppOwner($app)));
      $this->assertContains($app->getAppId(), $this->entityIdCache->getIds());
    }

    // Remove apps from cache by owner.
    $this->entityCache->removeAppsByOwner(reset($developer_apps)->getDeveloperId());
    $this->entityCache->removeAppsByOwner(reset($company_apps)->getCompanyName());

    foreach ($apps as $app) {
      $this->assertNull($this->entityCache->getEntity($app->getAppId()));
      $this->assertNull($this->entityCache->getAppsByOwner($this->entityCache->getAppOwner($app)));
      $this->assertNotContains($app->getAppId(), $this->entityIdCache->getIds());
    }

    $this->assertEmptyCaches();
  }

  /**
   * Tests developer app entity memory cache.
   */
  public function testDeveloperAppEntityMemoryCache() {
    $developer_app_cache_factory = $this->container->get('apigee_edge.entity.controller.cache.developer_app_cache_factory');

    // Owner of the developer apps.
    $developer = new Developer([
      'developerId' => $this->getRandomUniqueId(),
      'email' => strtolower($this->randomMachineName()) . '@example.com',
    ]);
    $developer_cache = $this->container->get('apigee_edge.controller.cache.developer');
    $developer_cache->saveEntities([$developer]);

    // Generate developer app entities with random data.
    $developer_apps = [];
    $id = $this->getRandomUniqueId();
    $developer_apps[$id] = new DeveloperApp([
      'appId' => $id,
      'name' => $this->getRandomUniqueId(),
      'developerId' => $developer->getDeveloperId(),
    ]);
    $developer_app_1 = $developer_apps[$id];

    $id = $this->getRandomUniqueId();
    $developer_apps[$id] = new DeveloperApp([
      'appId' => $id,
      'name' => $this->getRandomUniqueId(),
      'developerId' => $developer->getDeveloperId(),
    ]);
    $developer_app_2 = $developer_apps[$id];

    $cache_by_email = $developer_app_cache_factory->getAppCache($developer->getEmail());
    $cache_by_id = $developer_app_cache_factory->getAppCache($developer->getDeveloperId());

    $cache_by_email->saveEntities([$developer_app_1]);
    $cache_by_id->saveEntities([$developer_app_2]);
    $this->assertSame($developer_apps, $cache_by_email->getEntities());
    $this->assertSame($developer_apps, $cache_by_id->getEntities());

    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface $developer_app */
    foreach ($developer_apps as $developer_app) {
      // Load developer app by name.
      $this->assertSame($developer_app, $cache_by_email->getEntity($developer_app->getName()));
      $this->assertSame($developer_app, $cache_by_id->getEntity($developer_app->getName()));
    }

    // Remove developer app by name from developer app cache by email.
    $cache_by_email->removeEntities([$developer_app_1->getName()]);
    $this->assertNull($cache_by_email->getEntity($developer_app_1->getName()));
    $this->assertNull($cache_by_id->getEntity($developer_app_1->getName()));

    // Remove developer app by name from developer app cache by id.
    $cache_by_id->removeEntities([$developer_app_2->getName()]);
    $this->assertNull($cache_by_email->getEntity($developer_app_2->getName()));
    $this->assertNull($cache_by_id->getEntity($developer_app_2->getName()));

    $this->assertEmpty($cache_by_email->getEntities());
    $this->assertEmpty($cache_by_id->getEntities());
  }

  /**
   * Gets a random unique ID.
   */
  protected function getRandomUniqueId(): string {
    return uniqid('', TRUE);
  }

  /**
   * Saves entities into the memory cache and checks the result.
   *
   * @param \Apigee\Edge\Entity\EntityInterface[] $entities
   *   Apigee Edge entities to save into the cache.
   */
  protected function assertFullCaches(array $entities) {
    // Save the generated entities into the memory cache.
    $this->entityCache->saveEntities($entities);
    $this->assertSame($entities, $this->entityCache->getEntities());

    // Set cache states to TRUE.
    $this->entityCache->allEntitiesInCache(TRUE);
    $this->assertTrue($this->entityCache->isAllEntitiesInCache());
    $this->assertTrue($this->entityIdCache->isAllIdsInCache());
  }

  /**
   * Checks whether the cache is properly cleared.
   */
  protected function assertEmptyCaches() {
    $this->assertEmpty($this->entityCache->getEntities());
    $this->assertEmpty($this->entityIdCache->getIds());
    $this->assertFalse($this->entityCache->isAllEntitiesInCache());
    $this->assertFalse($this->entityIdCache->isAllIdsInCache());
  }

}
