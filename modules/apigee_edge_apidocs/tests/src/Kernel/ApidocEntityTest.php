<?php

/**
 * Copyright 2019 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\Tests\content_entity_example\Kernel;

use Drupal\apigee_edge_apidocs\Entity\ApiDoc;
use Drupal\KernelTests\KernelTestBase;

/**
 * Test basic CRUD operations for our ApiDoc entity type.
 *
 * @group apigee_edge
 * @group apigee_edge_apidocs
 */
class ApidocEntityTest extends KernelTestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  protected static $modules = [
    'user',
    'text',
    'file',
    'apigee_edge',
    'key',
    'apigee_edge_apidocs',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('apidoc');

    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Basic CRUD operations on a ApiDoc entity.
   */
  public function testEntity() {
    $entity = ApiDoc::create([
      'name' => 'API 1',
      'description' => 'Test API 1',
      'spec' => NULL,
      'api_product' => NULL,
    ]);
    $this->assertNotNull($entity);
    $this->assertEquals(SAVED_NEW, $entity->save());
    $this->assertEquals(SAVED_UPDATED, $entity->set('name', 'API 1a')->save());
    $entity_id = $entity->id();
    $this->assertNotEmpty($entity_id);
    $entity->delete();
    $this->assertNull(ApiDoc::load($entity_id));
  }

  /**
   * Test revisioning functionality on an apidocs entity.
   */
  public function testRevisions() {
    $description_v1 = 'Test API';
    $entity = ApiDoc::create([
      'name' => 'API 1',
      'description' => $description_v1,
      'spec' => NULL,
      'api_product' => NULL,
    ]);

    // Test saving a revision.
    $entity->setNewRevision();
    $entity->setRevisionLogMessage('v1');
    $entity->save();
    $v1_id = $entity->getRevisionId();
    $this->assertNotNull($v1_id);

    // Test saving a new revision.
    $new_log = 'v2';
    $entity->setDescription('Test API v2');
    $entity->setNewRevision();
    $entity->setRevisionLogMessage($new_log);
    $entity->save();
    $v2_id = $entity->getRevisionId();
    $this->assertTrue($v2_id > $v1_id);

    // Test saving without a new revision.
    $entity->setDescription('Test API v3');
    $entity->save();
    $this->assertTrue($v2_id === $entity->getRevisionId());

    // Test that the revision log message wasn't overriden.
    $this->assertEquals($new_log, $entity->getRevisionLogMessage());

    // Revert to the first revision.
    $entity_v1 = $this->entityTypeManager->getStorage('apidoc')
      ->loadRevision($v1_id);
    $entity_v1->setNewRevision();
    $entity_v1->isDefaultRevision(TRUE);
    $entity_v1->setRevisionLogMessage('Copy of revision ' . $v1_id);
    $entity_v1->save();

    // Load and check reverted values.
    $this->entityTypeManager->getStorage('apidoc')->resetCache();
    $reverted = ApiDoc::load($entity->id());
    $this->assertTrue($reverted->getRevisionId() > $v1_id);
    $this->assertTrue($reverted->isDefaultRevision());
    $this->assertEquals($description_v1, $reverted->getDescription());
  }

}
