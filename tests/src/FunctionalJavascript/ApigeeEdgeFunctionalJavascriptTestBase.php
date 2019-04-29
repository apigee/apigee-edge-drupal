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

namespace Drupal\Tests\apigee_edge\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\apigee_edge\Traits\ApigeeEdgeFunctionalTestTrait;

/**
 * Base class for functional javascript tests.
 */
abstract class ApigeeEdgeFunctionalJavascriptTestBase extends WebDriverTestBase {

  use ApigeeEdgeFunctionalTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->initTestEnv();
  }

  /**
   * {@inheritdoc}
   */
  public function createScreenshot($filename_prefix = '', $set_background_color = TRUE) {
    $log_dir = getenv('APIGEE_EDGE_TEST_LOG_DIR');
    if (!$log_dir) {
      $log_dir = $this->container->get('file_system')->realpath('public://');
    }
    $screenshots_dir = $log_dir . '/screenshots';
    if (!is_dir($screenshots_dir)) {
      mkdir($screenshots_dir, 0777, TRUE);
    }
    /** @var \Drupal\Core\Database\Connection $database */
    $database = $this->container->get('database');
    $test_id = str_replace('test', '', $database->tablePrefix());
    // Add table suffix (test id) to the file name and ensure the generated
    // file name is unique.
    $filename = file_create_filename("{$filename_prefix}-{$test_id}.png", $screenshots_dir);
    // Also create a log entry because that way we can understand the state of
    // the system before a screenshot got created more easily from logs.
    $this->container->get('logger.channel.apigee_edge_test')->debug("Creating new screenshot: {$filename}.");
    parent::createScreenshot($filename, $set_background_color);
  }

}
