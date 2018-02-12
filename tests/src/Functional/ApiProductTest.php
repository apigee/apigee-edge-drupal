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
use Drupal\Tests\BrowserTestBase;

/**
 * Create, delete, update API Product entity tests.
 *
 * @group apigee_edge
 */
class ApiProductTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'apigee_edge',
  ];

  /**
   * @var \Drupal\apigee_edge\Entity\Developer
   */
  protected $developer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->developer = Developer::create([
      'email' => $this->randomMachineName() . '@example.com',
      'userName' => $this->randomMachineName(),
      'firstName' => $this->randomMachineName(),
      'lastName' => $this->randomMachineName(),
      'status' => Developer::STATUS_ACTIVE,
    ]);
    $this->developer->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $this->developer->delete();

    parent::tearDown();
  }

  protected function resetCache() {
    \Drupal::entityTypeManager()->getStorage('api_product')->resetCache();
  }

  public function testCrud() {
    /** @var ApiProduct $apiproduct */
    $apiproduct = ApiProduct::create([
      'name' => $this->randomMachineName(),
      'displayName' => $this->randomMachineName(),
      'approvalType' => ApiProduct::APPROVAL_TYPE_AUTO,
    ]);

    $apiproduct->save();

    $this->assertNotEmpty($apiproduct->id());

    $this->resetCache();

    $apiproductlist = ApiProduct::loadMultiple();
    $this->assertContains($apiproduct->id(), array_keys($apiproductlist));

    $value = $this->randomMachineName();
    $apiproduct->setAttribute('test', $value);
    $apiproduct->save();

    $this->resetCache();

    /** @var ApiProduct $loadedApiProduct */
    $loadedApiProduct = ApiProduct::load($apiproduct->id());
    $this->assertEquals($value, $loadedApiProduct->getAttributeValue('test'));

    $apiproduct->delete();
  }

}
