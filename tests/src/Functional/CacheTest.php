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
use Drupal\Core\Url;

/**
 * Apigee Edge entity cache related tests.
 *
 * @group apigee_edge
 * @group apigee_edge_developer
 * @group apigee_edge_developer_app
 */
class CacheTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * The Drupal user that belongs to the developer app's developer.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The owner of the developer app.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * Developer app to test.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperAppInterface
   */
  protected $developerApp;

  /**
   * The cache backend.
   *
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
    $this->developerApp = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->uuid(),
    ]);
    $this->developerApp->save();

    $this->drupalLogin($this->account);
    $this->cacheBackend = $this->container->get('cache.apigee_edge_entity');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    if ($this->developer !== NULL) {
      try {
        $this->developer->delete();
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }
    parent::tearDown();
  }

  /**
   * Tests cache of Apigee Edge entities.
   */
  public function testCache() {
    $this->warmCaches();
    $this->credentialsTest();
    $this->warmCaches();
    $this->editAppIsAlwaysUncachedTest();
    $this->warmCaches();
    $this->userUpdatedTest();
    $this->warmCaches();
    $this->userDeletedTest();
  }

  /**
   * Tests that credentials are not cached, but found on the app page.
   */
  protected function credentialsTest() {
    $this->drupalGet(Url::fromRoute('entity.developer_app.canonical_by_developer', [
      'user' => $this->account->id(),
      'app' => $this->developerApp->getName(),
    ]));
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $loadedApp */
    $loadedApp = DeveloperApp::load($this->developerApp->id());
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

  /**
   * Tests that developer app edit form is always uncached.
   */
  protected function editAppIsAlwaysUncachedTest() {
    /** @var \Drupal\apigee_edge\SDKConnectorInterface $connector */
    $connector = $this->container->get('apigee_edge.sdk_connector');
    $controller = new DeveloperAppController($connector->getOrganization(), $this->developer->getDeveloperId(), $connector->getClient());
    $name = strtolower($this->randomMachineName(16));
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperApp $developer_app */
    $developer_app = $controller->load($this->developerApp->getName());
    $developer_app->setDisplayName($name);
    $controller->update($developer_app);
    $this->drupalGet(Url::fromRoute('entity.developer_app.edit_form_for_developer', [
      'user' => $this->account->id(),
      'app' => $developer_app->getName(),
    ]));
    $this->assertSession()->fieldValueEquals('displayName[0][value]', $name);
  }

  /**
   * Tests developer cache invalidation after editing user.
   */
  protected function userUpdatedTest() {
    $this->assertCacheInvalidation([
      "values:developer:{$this->developer->id()}",
      "values:developer_app:{$this->developerApp->id()}",
      "app_names:developer_app:{$this->developer->uuid()}:{$this->developerApp->getName()}",
    ], function () {
      $this->drupalPostForm(Url::fromRoute('entity.user.edit_form', ['user' => $this->account->id()]), [
        'first_name[0][value]' => $this->randomMachineName(),
        'last_name[0][value]' => $this->randomMachineName(),
      ], 'Save');
    });
  }

  /**
   * Tests developer cache invalidation after deleting user.
   */
  protected function userDeletedTest() {
    $this->assertCacheInvalidation([
      "values:developer:{$this->developer->id()}",
      "values:developer_app:{$this->developerApp->id()}",
      "app_names:developer_app:{$this->developer->uuid()}:{$this->developerApp->getName()}",
    ], function () {
      $this->drupalLogout();
      $this->account->delete();
      $this->account = NULL;
    });
  }

  /**
   * Cache rebuild.
   */
  protected function warmCaches() {
    $this->drupalGet(Url::fromRoute('entity.developer_app.collection_by_developer', ['user' => $this->account->id()]));
    $this->clickLink($this->developerApp->label());
  }

  /**
   * Check to see if the cache is invalidated.
   *
   * @param array $keys
   *   Cache keys to check.
   * @param callable $action
   *   Callable action that triggers invalidation.
   * @param bool $exists_before
   *   TRUE if the cache keys exist before the function call.
   * @param bool $exists_after
   *   FALSE if the cache keys should be removed after the function call.
   */
  protected function assertCacheInvalidation(array $keys, callable $action, bool $exists_before = TRUE, bool $exists_after = FALSE) {
    $this->assertKeys($keys, $exists_before);
    $action();
    $this->assertKeys($keys, $exists_after);
  }

  /**
   * Check to see if the given cache keys exist.
   *
   * @param array $keys
   *   Cache keys to check.
   * @param bool $exists
   *   TRUE if the cache keys should exist.
   */
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
