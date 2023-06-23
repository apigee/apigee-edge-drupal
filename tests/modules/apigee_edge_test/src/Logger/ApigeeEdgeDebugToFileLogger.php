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

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\syslog\Logger\SysLog;

/**
 * Logs Apigee Edge debug messages to a file in the root of Drupal.
 */
final class ApigeeEdgeDebugToFileLogger extends SysLog {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * ApigeeEdgeDebugToFileLogger constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A configuration factory instance.
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LogMessageParserInterface $parser, Connection $database) {
    parent::__construct($config_factory, $parser);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    // Only log messages from our debug module.
    if ($context['channel'] === 'apigee_edge_debug') {
      parent::log($level, $message, $context);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function syslogWrapper($level, $entry) {
    $log_path = getenv('APIGEE_EDGE_TEST_LOG_DIR');
    if (!$log_path) {
      $log_path = \Drupal::service('file_system')->realpath('public://');
    }
    // Add test prefix to the log file.
    // @phpstan-ignore-next-line
    $log_path .= '/apigee_edge_debug-' . str_replace('test', '', $this->database->tablePrefix()) . '.log';
    // Do not fail a test just because the fail is not writable.
    @error_log($entry . PHP_EOL, 3, $log_path);
  }

}
