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
 * Developer entity query test.
 *
 * @group apigee_edge
 */
class QueryTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * The developer entity storage.
   *
   * @var \Drupal\apigee_edge\Entity\Storage\DeveloperStorageInterface
   */
  protected $storage;

  /**
   * Random string for property prefixes.
   *
   * @var string
   */
  protected $prefix;

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
    [
      'email' => 'test03@example.com',
      'userName' => 'test03',
      'firstName' => 'Test03',
      'lastName' => 'User',
    ],
    [
      'email' => 'test04@example.com',
      'userName' => 'test04',
      'firstName' => 'Test04',
      'lastName' => 'User',
    ],
  ];

  /**
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface[]*/
  protected $edgeDevelopers = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->prefix = $this->randomMachineName();

    foreach ($this->developerData as $data) {
      $data['email'] = "{$this->prefix}.{$data['email']}";
      $developer = Developer::create($data);
      $developer->save();
      $this->edgeDevelopers[$data['email']] = $developer;
    }

    $this->storage = \Drupal::entityTypeManager()->getStorage('developer');
  }

  /**
   * Tests developer entity queries.
   */
  public function testQuery() {
    $query = $this->storage->getQuery();
    $query->condition('email', "{$this->prefix}.test", 'STARTS_WITH');
    $query->condition('email', '@example.com', 'ENDS_WITH');
    $query->sort('lastName');
    $query->sort('email', 'DESC');
    $results = $query->execute();
    $this->assertEquals(array_values([
      "{$this->prefix}.test04@example.com",
      "{$this->prefix}.test03@example.com",
      "{$this->prefix}.test02@example.com",
      "{$this->prefix}.test01@example.com",
      "{$this->prefix}.test00@example.com",
    ]), array_values($results));

    $query = $this->storage->getQuery();
    $query->condition('email', "{$this->prefix}.test", 'STARTS_WITH');
    $query->condition('email', '@example.com', 'ENDS_WITH');
    $query->sort('email');
    $query->range(1, 1);
    $results = $query->execute();
    $this->assertEquals(array_values(["{$this->prefix}.test01@example.com"]), array_values($results));

    $query = $this->storage->getQuery();
    $query->condition('email', "{$this->prefix}.test", 'STARTS_WITH');
    $query->condition('email', '@example.com', 'ENDS_WITH');
    $this->assertEquals(5, $query->count()->execute());
  }

  /**
   * Test for "smart" queries which are trying to reduce API calls.
   */
  public function testSmartQueries() {
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
    $result = $this->storage->getQuery()
      ->condition('email', NULL)
      ->count()->execute();
    $this->assertEquals(0, $result);
    $result = $this->storage->getQuery()
      ->condition('developerId', NULL)
      ->count()->execute();
    $this->assertEquals(0, $result);

    $developer = reset($this->edgeDevelopers);
    /** @var \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorageInterface $dev_app_storage */
    $dev_app_storage = \Drupal::entityTypeManager()->getStorage('developer_app');
    $result = $dev_app_storage->getQuery()
      ->condition('developerId', $developer->getDeveloperId())
      ->count()->execute();
    $this->assertEquals(1, $result);
    // If developer id - which can be used to filter apps directly on Apigee
    // Edge by calling the proper API endpoint - is set to something empty
    // we should get back an empty result.
    $result = $dev_app_storage->getQuery()
      ->condition('developerId', NULL)
      ->count()->execute();
    $this->assertEquals(0, $result);
    $result = $dev_app_storage->getQuery()
      ->condition('email', $developer->getEmail())
      ->count()->execute();
    $this->assertEquals(1, $result);
    // If developer email - which can be used to filter apps directly on Apigee
    // Edge by calling the proper API endpoint - is set to something empty
    // we should get back an empty result.
    $result = $dev_app_storage->getQuery()
      ->condition('email', NULL)
      ->count()->execute();
    $this->assertEquals(0, $result);
    // If app name is set to something empty then query should not fail and
    // we should get back an empty list even if the developer has apps.
    $result = $dev_app_storage->getQuery()
      ->condition('email', $developer->getEmail())
      ->condition('name', NULL)
      ->count()->execute();
    $this->assertEquals(0, $result);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    foreach ($this->edgeDevelopers as $edgeDeveloper) {
      $edgeDeveloper->delete();
    }
    parent::tearDown();
  }

}
