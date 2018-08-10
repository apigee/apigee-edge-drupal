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

use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperApp;

/**
 * Developer and developer app entity query test.
 *
 * @group apigee_edge
 * @group apigee_edge_developer
 * @group apigee_edge_developer_app
 */
class QueryTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * The developer entity storage.
   *
   * @var \Drupal\apigee_edge\Entity\Storage\DeveloperStorageInterface
   */
  protected $developerStorage;

  /**
   * The developer app entity storage.
   *
   * @var \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorageInterface
   */
  protected $developerAppStorage;

  /**
   * Random string for property prefixes.
   *
   * @var string
   */
  protected $prefix;

  /**
   * Data to create developers.
   *
   * @var array
   */
  protected $developerData = [
    [
      'email' => 'test00@example.com',
      'userName' => 'test00',
      'firstName' => 'Test00',
      'lastName' => 'User',
    ],
    [
      'email' => 'test01@example.com',
      'userName' => 'test01',
      'firstName' => 'Test01',
      'lastName' => 'User',
    ],
    [
      'email' => 'test02@example.com',
      'userName' => 'test02',
      'firstName' => 'Test02',
      'lastName' => 'User',
    ],
  ];

  /**
   * The created developer entities based on $this->developerData.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface[]
   */
  protected $edgeDevelopers = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->developerStorage = $this->container->get('entity_type.manager')->getStorage('developer');
    $this->developerAppStorage = $this->container->get('entity_type.manager')->getStorage('developer_app');
    $this->prefix = $this->randomMachineName();

    // Create developers.
    foreach ($this->developerData as $data) {
      $data['email'] = "{$this->prefix}.{$data['email']}";
      $developer = Developer::create($data);
      $developer->save();
      $this->edgeDevelopers[$data['email']] = $developer;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    foreach ($this->edgeDevelopers as $developer) {
      try {
        if ($developer !== NULL) {
          $developer->delete();
        }
      }
      catch (\Exception $exception) {
        $this->logException($exception);
      }
    }
    parent::tearDown();
  }

  /**
   * Tests developer and developer app entity queries.
   */
  public function testQueries() {
    $this->developerQueryTest();
    $this->smartQueryTest();
  }

  /**
   * Tests developer entity queries.
   */
  protected function developerQueryTest() {
    $result = $this->developerStorage->getQuery()
      ->condition('email', "{$this->prefix}.test", 'STARTS_WITH')
      ->condition('email', '@example.com', 'ENDS_WITH')
      ->sort('lastName')
      ->sort('email', 'DESC')
      ->execute();
    $this->assertEquals(array_values([
      "{$this->prefix}.test02@example.com",
      "{$this->prefix}.test01@example.com",
      "{$this->prefix}.test00@example.com",
    ]), array_values($result));

    $result = $this->developerStorage->getQuery()
      ->condition('email', "{$this->prefix}.test", 'STARTS_WITH')
      ->condition('email', '@example.com', 'ENDS_WITH')
      ->sort('email')
      ->range(1, 1)
      ->execute();
    $this->assertEquals(array_values(["{$this->prefix}.test01@example.com"]), array_values($result));

    $result = $this->developerStorage->getQuery()
      ->condition('email', "{$this->prefix}.test", 'STARTS_WITH')
      ->condition('email', '@example.com', 'ENDS_WITH')
      ->count()
      ->execute();
    $this->assertEquals(3, $result);
  }

  /**
   * Test for "smart" queries which are trying to reduce API calls.
   */
  protected function smartQueryTest() {
    // Make sure that all developer has an app.
    foreach ($this->edgeDevelopers as $developer) {
      $app = DeveloperApp::create([
        'name' => $this->randomMachineName(),
        'status' => DeveloperApp::STATUS_APPROVED,
        'developerId' => $developer->getDeveloperId(),
      ]);
      $app->save();
    }

    // When primary id(s) of entities is set to something empty we should
    // get back an empty result.
    $result = $this->developerStorage->getQuery()
      ->condition('email', NULL)
      ->count()
      ->execute();
    $this->assertEquals(0, $result);

    $result = $this->developerStorage->getQuery()
      ->condition('developerId', NULL)
      ->count()
      ->execute();
    $this->assertEquals(0, $result);

    $developer = reset($this->edgeDevelopers);
    $result = $this->developerAppStorage->getQuery()
      ->condition('developerId', $developer->getDeveloperId())
      ->count()
      ->execute();
    $this->assertEquals(1, $result);

    // If developer id - which can be used to filter apps directly on Apigee
    // Edge by calling the proper API endpoint - is set to something empty
    // we should get back an empty result.
    $result = $this->developerAppStorage->getQuery()
      ->condition('developerId', NULL)
      ->count()
      ->execute();
    $this->assertEquals(0, $result);

    $result = $this->developerAppStorage->getQuery()
      ->condition('email', $developer->getEmail())
      ->count()
      ->execute();
    $this->assertEquals(1, $result);

    // If developer email - which can be used to filter apps directly on Apigee
    // Edge by calling the proper API endpoint - is set to something empty
    // we should get back an empty result.
    $result = $this->developerAppStorage->getQuery()
      ->condition('email', NULL)
      ->count()
      ->execute();
    $this->assertEquals(0, $result);

    // If app name is set to something empty then query should not fail and
    // we should get back an empty list even if the developer has apps.
    $result = $this->developerAppStorage->getQuery()
      ->condition('email', $developer->getEmail())
      ->condition('name', NULL)
      ->count()
      ->execute();
    $this->assertEquals(0, $result);
  }

}
