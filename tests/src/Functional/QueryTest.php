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
use Drupal\Tests\BrowserTestBase;

/**
 * @group apigee_edge
 */
class QueryTest extends BrowserTestBase {

  public static $modules = [
    'apigee_edge',
  ];

  /**
   * @var \Drupal\apigee_edge\Entity\Storage\DeveloperStorageInterface
   */
  protected $storage;

  /**
   * @var string
   */
  protected $prefix;

  protected $edgeDevelopers = [
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
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->prefix = $this->randomMachineName();

    foreach ($this->edgeDevelopers as &$edgeDeveloper) {
      $edgeDeveloper['email'] = "{$this->prefix}.{$edgeDeveloper['email']}";
      Developer::create($edgeDeveloper)->save();
    }

    $this->storage = \Drupal::entityTypeManager()->getStorage('developer');
  }

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
   * {@inheritdoc}
   */
  protected function tearDown() {
    foreach ($this->edgeDevelopers as $edgeDeveloper) {
      Developer::load($edgeDeveloper['email'])->delete();
    }
    parent::tearDown();
  }

}
