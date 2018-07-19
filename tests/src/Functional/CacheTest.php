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

namespace Drupal\Tests\apigee_edge\Functional;

use Apigee\Edge\Api\Management\Controller\DeveloperAppController;
use Apigee\Edge\Api\Management\Entity\App;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;

class CacheTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * @var \Drupal\apigee_edge\Entity\Developer
   */
  protected $developer;

  /**
   * @var \Drupal\apigee_edge\Entity\DeveloperApp
   */
  protected $app;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->account = $this->createAccount([
      'create developer_app',
      'view own developer_app',
      'update own developer_app',
      'delete own developer_app',
    ]);
    $this->developer = Developer::load($this->account->getEmail());
    $this->app = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->uuid(),
    ]);
    $this->app->save();

    $this->drupalLogin($this->account);
    $this->warmCaches();

    $this->cacheBackend = \Drupal::service('cache.apigee_edge_entity');
  }

  protected function warmCaches() {
    $this->drupalGet("/user/{$this->account->id()}/apps");
    $this->clickLink($this->app->label());
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    if ($this->app) {
      try {
        $this->app->delete();
      }
      catch (\Exception $ex) {
      }
    }
    if ($this->account) {
      try {
        $this->account->delete();
      }
      catch (\Exception $ex) {
      }
    }
    parent::tearDown();
  }

  public function testUserDeleted() {
    $this->assertCacheInvalidation([
      "values:developer:{$this->developer->id()}",
      "values:developer_app:{$this->app->id()}",
      "app_names:developer_app:{$this->developer->uuid()}:{$this->app->getName()}",
    ], function () {
      $this->drupalLogout();
      $this->account->delete();
      $this->account = NULL;
    });
  }

  public function testUserUpdated() {
    $this->assertCacheInvalidation([
      "values:developer:{$this->developer->id()}",
      "values:developer_app:{$this->app->id()}",
      "app_names:developer_app:{$this->developer->uuid()}:{$this->app->getName()}",
    ], function () {
      $this->drupalPostForm("/user/{$this->account->id()}/edit", [
        'first_name[0][value]' => $this->randomMachineName(),
        'last_name[0][value]' => $this->randomMachineName(),
      ], 'Save');
    });
  }

  public function testEditAppIsAlwaysUncached() {
    /** @var \Drupal\apigee_edge\SDKConnectorInterface $connector */
    $connector = \Drupal::service('apigee_edge.sdk_connector');
    $controller = new DeveloperAppController($connector->getOrganization(), $this->developer->getDeveloperId(), $connector->getClient());
    $name = strtolower($this->randomMachineName(16));
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperApp $app */
    $app = $controller->load($this->app->getName());
    $app->setDisplayName($name);
    $controller->update($app);

    $this->drupalGet("/user/{$this->account->id()}/apps/{$app->getName()}/edit");
    $this->assertSession()->fieldValueEquals('displayName[0][value]', $name);
  }

  /**
   * Tests that credentials are not cached, but found on the app page.
   */
  public function testCredentials() {
    $this->drupalGet("/user/{$this->account->id()}/apps/{$this->app->getName()}");
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $loadedApp */
    $loadedApp = DeveloperApp::load($this->app->id());
    $this->assertNotEmpty($loadedApp, 'Developer App loaded');

    $rc = new \ReflectionClass($loadedApp);
    $properties = array_filter($rc->getProperties(), function (\ReflectionProperty $property) {
      return $property->getName() === 'credentials';
    });
    $this->assertCount(1, $properties, 'The credentials property found on the DeveloperApp class');
    /** @var \ReflectionProperty $property */
    $property = reset($properties);
    $property->setAccessible(TRUE);
    $cachedCredentials = $property->getValue($loadedApp);

    $this->assertEmpty($cachedCredentials, 'The credentials property is empty.');

    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential[] $credentials */
    $credentials = $loadedApp->getCredentials();
    $this->assertSession()->pageTextContains($credentials[0]->getConsumerKey());
    $this->assertSession()->pageTextContains($credentials[0]->getConsumerSecret());
  }

  protected function assertCacheInvalidation(array $keys, callable $action, bool $exists_before = TRUE, bool $exists_after = FALSE) {
    $this->assertKeys($keys, $exists_before);
    $action();
    $this->assertKeys($keys, $exists_after);
  }

  protected function assertKeys(array $keys, bool $exists) {
    foreach ($keys as $key) {
      $value = $this->cacheBackend->get($key);
      if ($exists) {
        $this->assertNotFalse($value, "Key found: {$key}");
      }
      else {
        $this->assertFalse($value, "Key not found: {$key}");
      }
    }
  }

}
