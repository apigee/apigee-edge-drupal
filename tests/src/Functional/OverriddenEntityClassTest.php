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

/**
 * Tests entity class overriding for the Edge entity types.
 *
 * @group apigee_edge
 */
class OverriddenEntityClassTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * Tests class overriding.
   */
  public function testClassOverride() {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $manager */
    $manager = $this->container->get('entity_type.manager');
    foreach (_apigee_edge_entity_class_mapping() as $entity_type => $entity_class) {
      $entity = $manager->getStorage($entity_type)->create();
      $this->assertInstanceOf($entity_class, $entity);
    }
  }

}
