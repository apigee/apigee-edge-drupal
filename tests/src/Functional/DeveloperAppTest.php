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

use Apigee\Edge\Api\Management\Entity\App;
use Apigee\Edge\Api\Management\Entity\AppCredentialInterface;
use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Controller\DeveloperAppCredentialController;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\apigee_edge\Event\AppCredentialCreateEvent;
use Drupal\apigee_edge\Event\AppCredentialDeleteEvent;
use Drupal\apigee_edge_test_app_keys\EventSubscriber\CreateDeleteAppKey;
use Drupal\Core\Entity\EntityInterface;

/**
 * Create, delete, update Developer App entity tests.
 *
 * @group apigee_edge
 * @group apigee_edge_developer_app
 */
class DeveloperAppTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * The Drupal user that belongs to the developer app's developer.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The owner of the developer app.
   *
   * @var \Drupal\apigee_edge\Entity\Developer
   */
  protected $developer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->account = $this->createAccount();
    $this->developer = Developer::load($this->account->getEmail());
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $this->developer->delete();

    parent::tearDown();
  }

  /**
   * Tests developer app entity.
   */
  public function testCrud() {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = $this->createApp();

    $this->assertNotEmpty($app->getAppId());

    $this->assertNotEmpty(DeveloperApp::load($app->id()));

    $applist = DeveloperApp::loadMultiple();
    $this->assertContains($app->id(), array_keys($applist));

    $value = $this->randomMachineName();
    $app->setAttribute('test', $value);
    $app->save();

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $loadedApp */
    $loadedApp = DeveloperApp::load($app->id());
    $this->assertEquals($value, $loadedApp->getAttributeValue('test'));

    $app->delete();
  }

  public function testAppCredentialEvents() {
    $validateCredential = function (AppCredentialInterface $credential) {
      $prefix = apigee_edge_test_app_keys_get_prefix();
      $this->assertStringStartsWith($prefix, $credential->getConsumerKey());
      $this->assertStringStartsWith($prefix, $credential->getConsumerSecret());
    };

    $this->installExtraModules(['apigee_edge_test_app_keys']);
    /** @var \Drupal\apigee_edge\Entity\DeveloperAppInterface $app */
    $app = $this->createApp();
    $credentials = $app->getCredentials();
    $credential = reset($credentials);
    $validateCredential($credential);
    $app->delete();

    // Override (default) app key when an app is created.
    $app = $this->createApp();
    $apiproduct = ApiProduct::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomMachineName(),
      'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
    ]);
    $apiproduct->save();
    $credentials = $app->getCredentials();
    $credential = reset($credentials);
    /** @var \Drupal\apigee_edge\SDKConnectorInterface $connector */
    $connector = $this->container->get('apigee_edge.sdk_connector');
    $dacc = new DeveloperAppCredentialController($connector->getOrganization(), $app->getDeveloperId(), $app->getName(), $connector->getClient());
    $dacc->delete($credential->getConsumerKey());

    // Override app key on generate.
    $dacc->generate([$apiproduct->id()], $app->getAttributes(), (string) $app->getCallbackUrl(), $app->getScopes(), 60 * 60 * 1000);
    // Also test that related caches got invalidated, this is the reason why
    // we retrieve the credentials from the app instead of use the return
    // value of the function above.
    $credentials = $app->getCredentials();
    $credential = reset($credentials);
    $validateCredential($credential);

    // Delete app key event.
    /** @var \Drupal\Core\State\StateInterface $states */
    $state = $this->container->get('state');
    $dacc->delete($credential->id());
    $this->assertNotNull($state->get(CreateDeleteAppKey::generateStateKey(AppCredentialDeleteEvent::APP_TYPE_DEVELOPER, $app->getDeveloperId(), $app->getName(), $credential->id())));

    // Create (additional) app key event.
    $credential_key = $this->randomMachineName();
    $dacc->create($credential_key, $this->randomMachineName());
    $this->assertNotNull($state->get(CreateDeleteAppKey::generateStateKey(AppCredentialCreateEvent::APP_TYPE_DEVELOPER, $app->getDeveloperId(), $app->getName(), $credential->id())));

    $app->delete();
    $apiproduct->delete();
  }

  /**
   * Creates new developer app.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createApp(): EntityInterface {
    $app = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $app->setOwner($this->account);
    $app->save();
    return $app;
  }

}
