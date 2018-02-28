<?php

/**
 * Copyright 2018 Google Inc.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301,
 * USA.
 */

namespace Drupal\apigee_edge_test\Logger;

use Drupal\syslog\Logger\SysLog;

/**
 * Logs Apigee Edge debug messages to a file in the root of Drupal.
 */
class DebugLogger extends SysLog {

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    // Only log messages from our debug module.
    if ($context['channel'] === 'apigee_edge_debug') {
      parent::log($level, $message, $context);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function syslogWrapper($level, $entry) {
    $log_path = getenv('APIGEE_EDGE_TEST_LOG_FILE');
    if (!$log_path) {
      $log_path = \Drupal::service('file_system')->realpath('public://apigee_edge_debug.log');
    }
    error_log($entry . PHP_EOL, 3, $log_path);
  }

}
