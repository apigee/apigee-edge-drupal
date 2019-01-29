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
class ApiDocTest extends KernelTestBase {

  protected static $modules = ['user', 'text', 'file', 'apigee_edge_apidocs'];

  /**
   * Basic CRUD operations on a ApiDoc entity.
   */
  public function testEntity() {
    $this->installEntitySchema('apidoc');
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

}
