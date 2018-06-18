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

use Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;
use Drupal\Tests\apigee_edge\Functional\ApigeeEdgeTestTrait;

/**
 * Base class for functional javascript tests.
 */
abstract class ApigeeEdgeFunctionalJavascriptTestBase extends JavascriptTestBase {

  use ApigeeEdgeTestTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct($name = NULL, array $data = [], $dataName = '') {
    // Use DrupalSelenium2Driver instead of PhantomJSDriver.
    $this->minkDefaultDriverClass = DrupalSelenium2Driver::class;
    parent::__construct($name, $data, $dataName);
  }

  /**
   * {@inheritdoc}
   */
  public function createScreenshot($filename_prefix = '', $set_background_color = TRUE) {
    $log_path = getenv('APIGEE_EDGE_TEST_LOG_DIR');
    if (!$log_path) {
      $log_path = \Drupal::service('file_system')->realpath('public://');
    }
    /** @var \Drupal\Core\Database\Connection $database */
    $database = \Drupal::service('database');
    $test_id = str_replace('test', '', $database->tablePrefix());
    // Add table suffix (test id) to the file name and ensure the generated
    // file name is unique.
    $filename = file_create_filename("{$filename_prefix}-{$test_id}.jpg", $log_path . '/screenshots');
    // Also create a log entry because that way we can understand the state of
    // the system before a screenshot got created more easily from logs.
    \Drupal::logger('apigee_edge')->debug("Creating new screenshot: {$filename}.");
    parent::createScreenshot($filename, $set_background_color);
  }

}
