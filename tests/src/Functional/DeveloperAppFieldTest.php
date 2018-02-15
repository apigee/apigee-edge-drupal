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
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\field_ui\Tests\FieldUiTestTrait;

class DeveloperAppFieldTest extends ApigeeEdgeFunctionalTestBase {

  use FieldUiTestTrait;

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * @var \Drupal\apigee_edge\Entity\Developer
   */
  protected $developer;

  protected function setUp() {
    $this->profile = 'standard';
    parent::setUp();

    $this->account = $this->createAccount([
      'administer apigee edge',
      'administer developer_app',
      'administer display modes',
      'administer developer_app fields',
      'administer developer_app form display',
      'administer developer_app display',
    ]);
    $this->developer = Developer::load($this->account->getEmail());
    $this->drupalLogin($this->account);
  }

  protected function tearDown() {
    $this->drupalLogout();
    $this->account->delete();
    parent::tearDown();
  }

  /**
   * @dataProvider fieldDataProvider
   */
  public function testField(string $field_type, array $storage_edit, array $field_edit, array $field_data) {
    $field_name = strtolower($this->randomMachineName());
    $this->fieldUIAddNewField(
      '/admin/config/apigee-edge/app-settings',
      $field_name, strtoupper($field_name),
      $field_type,
      $storage_edit,
      $field_edit
    );
    drupal_flush_all_caches();

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);
    $app->setOwner($this->account);
    $app->set($field_name, $field_data);
    $app->save();

    drupal_flush_all_caches();

    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $loadedApp */
    $loadedApp = DeveloperApp::load($app->id());

    $this->assertEquals($field_data, $loadedApp->get($field_name)->getValue());

    $app->delete();
  }

  public function fieldDataProvider() {
    return [
      'link' => [
        'link',
        [],
        [],
        [
          [
            'uri' => 'http://example.com',
            'title' => 'Example',
            'options' => [],
          ],
        ],
      ],
      'long text' => [
        'text_long',
        [
          'cardinality' => -1,
        ],
        [],
        [
          [
            'value' => $this->getRandomGenerator()->paragraphs(),
          ],
          [
            'value' => $this->getRandomGenerator()->paragraphs(),
          ],
          [
            'value' => $this->getRandomGenerator()->paragraphs(),
          ],
          [
            'value' => $this->getRandomGenerator()->paragraphs(),
          ],
          [
            'value' => $this->getRandomGenerator()->paragraphs(),
          ],
        ],
      ],
    ];
  }

  public function testTypes() {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $app */
    $app = DeveloperApp::create([
      'name' => $this->randomMachineName(),
      'status' => App::STATUS_APPROVED,
      'developerId' => $this->developer->getDeveloperId(),
    ]);

    $field_values = [
      'scopes' => ['a', 'b', 'c'],
      'displayName' => $this->getRandomGenerator()->word(16),
      'createdAt' => time(),
    ];

    foreach ($field_values as $field_name => $field_value) {
      $app->set($field_name, $field_value);
    }

    $app->preSave(new class() implements EntityStorageInterface {

      public function resetCache(array $ids = NULL) {}

      public function loadMultiple(array $ids = NULL) {}

      public function load($id) {}

      public function loadUnchanged($id) {}

      public function loadRevision($revision_id) {}

      public function deleteRevision($revision_id) {}

      public function loadByProperties(array $values = []) {}

      public function create(array $values = []) {}

      public function delete(array $entities) {}

      public function save(EntityInterface $entity) {}

      public function hasData() {}

      public function getQuery($conjunction = 'AND') {}

      public function getAggregateQuery($conjunction = 'AND') {}

      public function getEntityTypeId() {}

      public function getEntityType() {}

    });

    foreach ($field_values as $field_name => $field_value) {
      $getter = 'get' . ucfirst($field_name);
      $this->assertEquals($field_value, call_user_func([$app, $getter]));
    }
  }

}
