<?php

/**
 * Copyright 2020 Google Inc.
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

namespace Drupal\Tests\apigee_edge\Unit\Entity;

use Drupal\Tests\UnitTestCase;
use Drupal\apigee_edge\Entity\ApiProduct;

/**
 * Test ApiProductTest class.
 *
 * @group apigee_edge
 */
class ApiProductTest extends UnitTestCase {

  /**
   * The API Product under test.
   *
   * @var \Drupal\apigee_edge\Entity\ApiProduct
   */
  private $apiProduct;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->apiProduct = new ApiProduct([]);
  }

  /**
   * Test setting proxies.
   */
  public function testSetProxies() {
    $proxies_expected = ['proxy1', 'proxy2'];
    $this->apiProduct->setProxies(...$proxies_expected);
    $proxies_actual = $this->apiProduct->getProxies();
    $this->assertEquals($proxies_expected, $proxies_actual);

  }

  /**
   * Test setting scopes.
   */
  public function testSetScopes() {
    $scopes_expected = ['scope1', 'scope2'];
    $this->apiProduct->setScopes(...$scopes_expected);
    $scopes_actual = $this->apiProduct->getScopes();
    $this->assertEquals($scopes_expected, $scopes_actual);

  }

  /**
   * Test setting environments.
   */
  public function testSetEnvironments() {
    $environments_expected = ['environment1', 'environment2'];
    $this->apiProduct->setEnvironments(...$environments_expected);
    $environments_actual = $this->apiProduct->getEnvironments();
    $this->assertEquals($environments_expected, $environments_actual);
  }

}
