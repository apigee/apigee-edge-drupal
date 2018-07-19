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

namespace Drupal\apigee_edge_debug;

use Drupal\apigee_edge\SDKConnector as OriginalSDKConnector;

/**
 * Service decorator for SDKConnector.
 */
class SDKConnector extends OriginalSDKConnector {

  /**
   * Customer http request header.
   *
   * This tells the ApiClientProfiler to profile requests made by the underlying
   * HTTP client.
   *
   * @see \Drupal\apigee_edge_debug\HttpClientMiddleware\ApiClientProfiler
   */
  public const HEADER = 'X-Apigee-Edge-Api-Client-Profiler';

  /**
   * The inner SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnector
   */
  private $innerService;

  /**
   * {@inheritdoc}
   */
  protected function httpClientConfiguration(): array {
    $config = parent::httpClientConfiguration();
    $config['headers'][static::HEADER] = static::HEADER;
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function __call($method, $args) {
    return call_user_func_array([$this->innerService, $method], $args);
  }

}
