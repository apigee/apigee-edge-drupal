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

    // Disable API products field on the developer app form so we can submit
    // the form without creating products.
    $this->config('apigee_edge.common_app_settings')->set('user_select', FALSE)->save();

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
    $this->developerDeletedTest();
  }

  /**
   * Tests that credentials are not cached, but still available if needed.
   */
  protected function credentialsTest() {
    /** @var \Drupal\apigee_edge_test\Entity\Storage\DeveloperAppStorage $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('developer_app');

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $loadedApp */
    $loadedApp = $storage->load($this->developerApp->id());
    $this->assertNotEmpty($loadedApp, 'Developer App loaded');

    $cached_apps = $storage->getFromCache([$loadedApp->id()]);
    /** @var \Drupal\apigee_edge\Entity\AppInterface $cached_app */
    $cached_app = reset($cached_apps);

    // They are not in the cached SDK entity...
    $this->assertEmpty($cached_app->decorated()->getCredentials(), 'The credentials property is empty.');
    $credentials = $loadedApp->getCredentials();
    // But they still available in the Drupal entity.
    $this->assertNotEmpty($credentials, 'The credentials property is not empty.');
    // And visible on the UI.
    /** @var \Apigee\Edge\Api\Management\Entity\AppCredential[] $credentials */
    $this->drupalGet(Url::fromRoute('entity.developer_app.canonical_by_developer', [
      'user' => $this->account->id(),
      'app' => $this->developerApp->getName(),
    ]));
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
    // Submit the form to clear render caches.
    $this->submitForm([], 'Save');
    // Update the label of the "cached" developer app entity so the next
    // warmCaches() method call could find the related link to that on the
    // Apps page.
    $this->developerApp->setDisplayName($developer_app->getDisplayName());
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
   * Tests developer and developer app cache invalidation after user removal.
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
   * Tests developer & developer app cache invalidation after developer removal.
   *
   * Developer apps of a developer must be removed from cache even when a
   * developer _entity_ gets deleted _programmatically_ (and not the related
   * Drupal user).
   */
  public function developerDeletedTest() {
    $data = [
      'firstName' => $this->randomString(),
      'lastName' => $this->randomString(),
      'userName' => $this->randomMachineName(),
    ];
    $data['email'] = $this->randomMachineName() . ".{$data['userName']}@example.com";
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
    $developer = Developer::create($data);
    $developer->save();
    // Warm up cache.
    $developer = Developer::load($developer->id());
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $developerApp */
    $developerApp = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => DeveloperApp::STATUS_APPROVED,
      'developerId' => $developer->uuid(),
    ]);
    try {
      $developerApp->save();
    }
    catch (\Exception $e) {
      $developer->delete();
      throw $e;
    }
    // Warm up cache.
    $developerApp = DeveloperApp::load($developerApp->id());
    $this->assertCacheInvalidation([
      "values:developer:{$developer->id()}",
      "values:developer:{$developer->uuid()}",
      // The two above should be the same, so this is just a sanity check.
      "values:developer_app:{$developerApp->id()}",
      "values:developer_app:{$developerApp->uuid()}",
      "app_names:developer_app:{$developer->uuid()}:{$developerApp->getName()}",
    ], function () use ($developer) {
      try {
        // If this fails, it would fail in the teardown as well.
        $developer->delete();
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
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
   * @param bool $should_exist
   *   TRUE if the cache keys should exist.
   */
  protected function assertKeys(array $keys, bool $should_exist) {
    foreach ($keys as $key) {
      $value = $this->cacheBackend->get($key);
      if ($should_exist) {
        $this->assertNotFalse($value, "Cache key has not found when it should: {$key}");
      }
      else {
        $this->assertFalse($value, "Cache key found when it should not: {$key}");
      }
    }
  }

}
