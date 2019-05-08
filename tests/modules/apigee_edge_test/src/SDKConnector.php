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

namespace Drupal\apigee_edge_test;

use Apigee\Edge\Client;
use Apigee\Edge\ClientInterface;
use Apigee\Edge\Exception\ApiResponseException;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\key\KeyInterface;
use Http\Client\Exception;
use Http\Message\Authentication;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Service decorator for SDKConnector.
 */
final class SDKConnector implements SDKConnectorInterface {

  /**
   * The decorated SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnectorInterface
   */
  private $innerService;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

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
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(SDKConnectorInterface $inner_service, LoggerInterface $logger) {
    $this->innerService = $inner_service;
    $this->logger = $logger;
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
    // If method got called without default parameters, initialize and/or
    // return the default API client from the internal cache.
    if (!isset($authentication, $endpoint) && empty($options)) {
      if ($this->defaultClient === NULL) {
        $this->defaultClient = $this->innerService->getClient($authentication, $endpoint, $this->getOptionsWithRetryPlugin($options));
      }

      return $this->defaultClient;
    }

    return $this->innerService->getClient($authentication, $endpoint, $this->getOptionsWithRetryPlugin($options));
  }

  /**
   * Returns the options array with the retry decider plugin.
   *
   * @param array $options
   *   Array if API client options.
   *
   * @return array
   *   Array of API client options.
   */
  private function getOptionsWithRetryPlugin(array $options): array {
    $decider = function (RequestInterface $request, Exception $e) {
      // Only retry API calls that failed with this specific error.
      if ($e instanceof ApiResponseException && $e->getEdgeErrorCode() === 'messaging.adaptors.http.flow.ApplicationNotFound') {
        $this->logger->warning('Restarting request because it failed. {error_code}: {exception}.', [
          'error_code' => $e->getEdgeErrorCode(),
          'exception' => $e->getMessage(),
        ]);

        return TRUE;
      }

      return FALSE;
    };
    // Use the retry plugin in tests.
    return array_merge($options, [
      Client::CONFIG_RETRY_PLUGIN_CONFIG => [
        'retries' => 5,
        'exception_decider' => $decider,
        'exception_delay' => static function (RequestInterface $request, Exception $e, $retries) : int {
          return $retries * 15000000;
        },
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function testConnection(KeyInterface $key = NULL): void {
    $this->innerService->testConnection($key);
  }

}
