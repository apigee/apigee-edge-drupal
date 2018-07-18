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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge_debug\Plugin\DebugMessageFormatter;

use GuzzleHttp\TransferStats;
use Http\Message\Formatter;
use Http\Message\Formatter\CurlCommandFormatter as OriginalCurlCommandFormatter;

/**
 * CURL command message formatter plugin.
 *
 * Output API request as a cURL command. It does not render response- or
 * transfer statistics data.
 *
 * @DebugMessageFormatter(
 *   id = "curl",
 *   label = @Translation("cURL command"),
 * )
 */
class CurlCommandFormatter extends DebugMessageFormatterPluginBase {

  /**
   * The original cURL command formatter.
   *
   * @var \Http\Message\Formatter\CurlCommandFormatter
   */
  private static $formatter;

  /**
   * {@inheritdoc}
   */
  public function formatStats(TransferStats $stats): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  protected function getFormatter(): Formatter {
    if (NULL === self::$formatter) {
      self::$formatter = new OriginalCurlCommandFormatter();
    }
    return self::$formatter;
  }

}
