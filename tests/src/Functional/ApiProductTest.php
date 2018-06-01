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

use Drupal\apigee_edge\Entity\ApiProduct;
use Drupal\apigee_edge\Entity\Developer;

/**
 * Create, delete, update API Product entity tests.
 *
 * @group apigee_edge
 * @group apigee_edge_api_product
 */
class ApiProductTest extends ApigeeEdgeFunctionalTestBase {

  /**
   * Tests API product entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function testCrud() {
    /** @var \Drupal\apigee_edge\Entity\ApiProduct $apiproduct */
    $apiproduct = ApiProduct::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomMachineName(),
      'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
    ]);

    $apiproduct->save();

    $this->assertNotEmpty($apiproduct->id());

    $apiproductlist = ApiProduct::loadMultiple();
    $this->assertContains($apiproduct->id(), array_keys($apiproductlist));

    $value = $this->randomMachineName();
    $apiproduct->setAttribute('test', $value);
    $apiproduct->save();

    /** @var \Drupal\apigee_edge\Entity\ApiProduct $loadedApiProduct */
    $loadedApiProduct = ApiProduct::load($apiproduct->id());
    $this->assertEquals($value, $loadedApiProduct->getAttributeValue('test'));

    $apiproduct->delete();
  }

}
