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

use Apigee\Edge\ClientInterface;
use Drupal\apigee_edge\SDKConnector as OriginalSDKConnector;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\key\KeyInterface;
use Http\Message\Authentication;

/**
 * Service decorator for SDKConnector.
 */
final class SDKConnector implements SDKConnectorInterface {

  /**
   * Custom HTTP request header.
   *
   * This tells the ApiClientProfiler to profile requests made by the underlying
   * HTTP client.
   *
   * @see \Drupal\apigee_edge_debug\HttpClientMiddleware\ApiClientProfiler
   *
   * @var string
   */
  public const HEADER = 'X-Apigee-Edge-Api-Client-Profiler';

  /**
   * The inner SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $innerService;

  /**
   * The API client initialized from the saved credentials and default config.
   *
   * @var null|\Apigee\Edge\ClientInterface
   *
   * @see getClient()
   */
  private $defaultClient;

  /**
   * Constructs a new SDKConnector.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $inner_service
   *   The decorated SDK connector service.
   */
  public function __construct(SDKConnectorInterface $inner_service) {
    $this->innerService = $inner_service;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrganization(): string {
    return $this->innerService->getOrganization();
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(?Authentication $authentication = NULL, ?string $endpoint = NULL, array $options = []): ClientInterface {
    $extra_options[OriginalSDKConnector::CLIENT_FACTORY_OPTIONS]['headers'][static::HEADER] = static::HEADER;

    // If method got called without default parameters, initialize and/or
    // return the default API client from the internal cache.
    if (!isset($authentication, $endpoint) && empty($options)) {
      if ($this->defaultClient === NULL) {
        $this->defaultClient = $this->innerService->getClient($authentication, $endpoint, $extra_options);
      }

      return $this->defaultClient;
    }

    return $this->innerService->getClient($authentication, $endpoint, array_merge($options, $extra_options));
  }

  /**
   * {@inheritdoc}
   */
  public function testConnection(KeyInterface $key = NULL): void {
    $this->innerService->testConnection($key);
  }

}
