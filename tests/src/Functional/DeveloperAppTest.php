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
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;

/**
 * Create, delete, update Developer App entity tests.
 *
 * @group apigee_edge
 * @group apigee_edge_developer_app
 */
class DeveloperAppTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * @var \Drupal\apigee_edge\Entity\Developer
   */
  protected $developer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->profile = 'standard';
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

  protected function resetCache() {
    \Drupal::entityTypeManager()->getStorage('developer_app')->resetCache();
  }

  public function testCrud() {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $app->setOwner($this->account);
    $app->save();

    $this->assertNotEmpty($app->getAppId());

    $this->resetCache();

    $this->assertNotEmpty(DeveloperApp::load($app->id()));

    $applist = DeveloperApp::loadMultiple();
    $this->assertContains($app->id(), array_keys($applist));

    $value = $this->randomMachineName();
    $app->setAttribute('test', $value);
    $app->save();

    $this->resetCache();

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $loadedApp */
    $loadedApp = DeveloperApp::load($app->id());
    $this->assertEquals($value, $loadedApp->getAttributeValue('test'));

    $app->delete();
  }

}
