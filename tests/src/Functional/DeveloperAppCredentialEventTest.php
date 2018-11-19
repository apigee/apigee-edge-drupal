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
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\apigee_edge\Event\AppCredentialCreateEvent;
use Drupal\apigee_edge\Event\AppCredentialDeleteEvent;
use Drupal\apigee_edge_test_app_keys\EventSubscriber\CreateDeleteAppKey;

/**
 * Developer app keys, credential event test.
 *
 * @group apigee_edge
 * @group apigee_edge_developer_app
 */
class DeveloperAppCredentialEventTest extends ApigeeEdgeFunctionalTestBase {

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
   * API product to test.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProductInterface
   */
  protected $apiProduct;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installExtraModules(['apigee_edge_test_app_keys']);

    $this->account = $this->createAccount();
    $this->developer = Developer::load($this->account->getEmail());

    $this->developerApp = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $this->developerApp->setOwner($this->account);
    $this->developerApp->save();

    $this->apiProduct = ApiProduct::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomMachineName(),
      'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
    ]);
    $this->apiProduct->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    try {
      if ($this->developer !== NULL) {
        $this->developer->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    try {
      if ($this->apiProduct !== NULL) {
        $this->apiProduct->delete();
      }
    }
    catch (\Exception $exception) {
      $this->logException($exception);
    }
    parent::tearDown();
  }

  /**
   * Developer app keys, credential event test.
   */
  public function testAppCredentialEvents() {
    $validateCredential = function (AppCredentialInterface $credential) {
      $prefix = apigee_edge_test_app_keys_get_prefix();
      $this->assertStringStartsWith($prefix, $credential->getConsumerKey());
      $this->assertStringStartsWith($prefix, $credential->getConsumerSecret());
    };

    $credentials = $this->developerApp->getCredentials();
    $credential = reset($credentials);
    $validateCredential($credential);

    // Override (default) app key when an app is created.
    $credentials = $this->developerApp->getCredentials();
    $credential = reset($credentials);
    $dacc = $this->container->get('apigee_edge.controller.developer_app_credential_factory')->developerAppCredentialController($this->developerApp->getDeveloperId(), $this->developerApp->getName());
    $dacc->delete($credential->getConsumerKey());

    // Override app key on generate.
    $dacc->generate([$this->apiProduct->id()], $this->developerApp->getAttributes(), (string) $this->developerApp->getCallbackUrl(), $this->developerApp->getScopes(), 60 * 60 * 1000);
    // Also test that related caches got invalidated, this is the reason why
    // we retrieve the credentials from the app instead of use the return
    // value of the function above.
    $credentials = $this->developerApp->getCredentials();
    $credential = reset($credentials);
    $validateCredential($credential);

    // Delete app key event.
    $state = $this->container->get('state');
    $dacc->delete($credential->id());
    $this->assertNotNull($state->get(CreateDeleteAppKey::generateStateKey(AppCredentialDeleteEvent::APP_TYPE_DEVELOPER, $this->developerApp->getDeveloperId(), $this->developerApp->getName(), $credential->id())));

    // Create (additional) app key event.
    $credential_key = $this->randomMachineName();
    $dacc->create($credential_key, $this->randomMachineName());
    $this->assertNotNull($state->get(CreateDeleteAppKey::generateStateKey(AppCredentialCreateEvent::APP_TYPE_DEVELOPER, $this->developerApp->getDeveloperId(), $this->developerApp->getName(), $credential->id())));
  }

}
