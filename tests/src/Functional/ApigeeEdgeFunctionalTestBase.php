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

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\apigee_edge\Traits\ApigeeEdgeFunctionalTestTrait;
use Drupal\apigee_edge\Plugin\EdgeKeyTypeInterface;

/**
 * Base class for functional tests.
 */
abstract class ApigeeEdgeFunctionalTestBase extends BrowserTestBase {

  use ApigeeEdgeFunctionalTestTrait;

  /**
   * For tests relying on no markup at all or at least no core markup.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Skipping the test if instance type is Public.
    $instance_type = getenv('APIGEE_EDGE_INSTANCE_TYPE');
    if (!empty($instance_type) && $instance_type === EdgeKeyTypeInterface::INSTANCE_TYPE_HYBRID) {
      $this->markTestSkipped('This test suite is expecting a PUBLIC instance type.');
    }
    parent::setUp();
    $this->initTestEnv();
  }

}
