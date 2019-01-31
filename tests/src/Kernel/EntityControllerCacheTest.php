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
use Drupal\Tests\apigee_edge\Traits\EntityControllerCacheUtilsTrait;

/**
 * Apigee Edge entity controller cache tests.
 *
 * @group apigee_edge
 * @group apigee_edge_kernel
 */
class EntityControllerCacheTest extends KernelTestBase {

  use EntityControllerCacheUtilsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'apigee_edge',
    'key',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
  }

  /**
   * Tests developer entity controller cache.
   */
  public function testDeveloperEntityControllerCache() {
    $developer_cache = $this->container->get('apigee_edge.controller.cache.developer');
    $developer_id_cache = $this->container->get('apigee_edge.controller.cache.developer_ids');

    // Generate developer entities with random data.
    $developers = [];
    for ($i = 0; $i < 3; $i++) {
      $id = $this->getRandomUniqueId();
      $developers[$id] = new Developer([
        'developerId' => $id,
        'email' => strtolower($this->randomMachineName()) . '@example.com',
      ]);
    }

    $this->saveAllEntitiesAndValidate($developers, $developer_cache, $developer_id_cache);

    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperInterface $developer */
    foreach ($developers as $developer) {
      // Load developer by email and by id.
      $this->assertSame($developer, $developer_cache->getEntity($developer->getEmail()));
      $this->assertSame($developer, $developer_cache->getEntity($developer->getDeveloperId()));
      $this->assertContains($developer->getEmail(), $developer_id_cache->getIds());

      // Pass an invalid entity id to ensure it does not cause any trouble
      // anymore.
      // @see \Drupal\apigee_edge\Entity\Controller\Cache\EntityCache::removeEntities()
      // @see https://www.drupal.org/project/drupal/issues/3017753
      $developer_cache->removeEntities([$developer->getDeveloperId(), $this->getRandomGenerator()->string()]);
      $this->assertNull($developer_cache->getEntity($developer->getEmail()));
      $this->assertNull($developer_cache->getEntity($developer->getDeveloperId()));
      $this->assertNotContains($developer->getEmail(), $developer_id_cache->getIds());
    }

    $this->assertEmptyCaches($developer_cache, $developer_id_cache);
  }

  /**
   * Tests app entity controller cache.
   */
  public function testAppEntityControllerCache() {
    $app_cache = $this->container->get('apigee_edge.controller.cache.apps');
    $app_id_cache = $this->container->get('apigee_edge.controller.cache.app_ids');

    // Generate developer app entities with random data.
    $developer_apps = [];
    $parent_id = $this->getRandomUniqueId();
    for ($i = 0; $i < 2; $i++) {
      $id = $this->getRandomUniqueId();
      $developer_apps[$id] = new DeveloperApp([
        'appId' => $id,
        'name' => $this->getRandomGenerator()->name(),
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
        'name' => $this->getRandomGenerator()->name(),
        'companyName' => $parent_id,
      ]);
    }

    $apps = $developer_apps + $company_apps;

    $this->saveAllEntitiesAndValidate($apps, $app_cache, $app_id_cache);

    /** @var \Apigee\Edge\Api\Management\Entity\AppInterface $app */
    foreach ($apps as $app) {
      // Load app by id and by owner (developer uuid or company name).
      $this->assertSame($app, $app_cache->getEntity($app->getAppId()));
      $this->assertContains($app, $app_cache->getAppsByOwner($app_cache->getAppOwner($app)));
      $this->assertContains($app->getAppId(), $app_id_cache->getIds());
    }

    // Remove apps from cache by owner.
    $app_cache->removeAppsByOwner(reset($developer_apps)->getDeveloperId());
    $app_cache->removeAppsByOwner(reset($company_apps)->getCompanyName());

    foreach ($apps as $app) {
      $this->assertNull($app_cache->getEntity($app->getAppId()));
      $this->assertNull($app_cache->getAppsByOwner($app_cache->getAppOwner($app)));
      $this->assertNotContains($app->getAppId(), $app_id_cache->getIds());
    }

    $this->assertEmptyCaches($app_cache, $app_id_cache);
  }

  /**
   * Tests developer app entity controller cache.
   */
  public function testDeveloperAppEntityControllerCache() {
    $developer_app_cache_factory = $this->container->get('apigee_edge.entity.controller.cache.developer_app_cache_factory');

    // Owner of the developer apps. It should be saved into the developer cache
    // because developer app cache tries to load the owner email from the
    // developer cache to reduce API calls.
    $developer = new Developer([
      'developerId' => $this->getRandomUniqueId(),
      'email' => strtolower($this->randomMachineName()) . '@example.com',
    ]);
    $developer_cache = $this->container->get('apigee_edge.controller.cache.developer');
    $developer_cache->saveEntities([$developer]);

    $developer_apps = [];
    for ($i = 0; $i < 2; $i++) {
      $id = $this->getRandomUniqueId();
      $developer_apps[$id] = new DeveloperApp([
        'appId' => $id,
        'name' => $this->getRandomGenerator()->name(),
        'developerId' => $developer->getDeveloperId(),
      ]);
    }
    list($developer_app_1, $developer_app_2) = array_values($developer_apps);

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

}
