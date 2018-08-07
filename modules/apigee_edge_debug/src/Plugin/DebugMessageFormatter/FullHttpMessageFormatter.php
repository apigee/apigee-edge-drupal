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
use Http\Message\Formatter\FullHttpMessageFormatter as OriginalFullHttpMessageFormatter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Full HTML debug message formatter plugin.
 *
 * @DebugMessageFormatter(
 *   id = "full_html",
 *   label = @Translation("Full HTML"),
 * )
 */
class FullHttpMessageFormatter extends DebugMessageFormatterPluginBase {

  /**
   * The maximum length of the body.
   *
   * @var int
   */
  private $maxBodyLength = 10000;

  /**
   * The original full HTTP message formatter.
   *
   * @var \Http\Message\Formatter\FullHttpMessageFormatter
   */
  private static $formatter;

  /**
   * {@inheritdoc}
   */
  public function formatStats(TransferStats $stats): string {
    $output = '';
    foreach ($this->getTimeStatsInSeconds($stats) as $key => $value) {
      $output .= "{$key}: $value\r\n";
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFormatter(): Formatter {
    if (NULL === self::$formatter) {
      // The default 1000 characters is not enough.
      self::$formatter = new OriginalFullHttpMessageFormatter($this->maxBodyLength);
    }
    return self::$formatter;
  }

  /**
   * {@inheritdoc}
   */
  public function formatResponse(ResponseInterface $response, RequestInterface $request): string {
    return sprintf("Response body got truncated to %d characters.\n\n%s", $this->maxBodyLength, parent::formatResponse($response, $request));
  }

}
