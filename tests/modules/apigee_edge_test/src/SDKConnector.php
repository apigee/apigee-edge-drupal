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
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\apigee_edge\SDKConnector as DecoratedSDKConnector;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\key\KeyRepositoryInterface;
use Http\Client\Exception;
use Http\Message\Authentication;
use Psr\Http\Message\RequestInterface;

/**
 * Service decorator for SDKConnector.
 */
final class SDKConnector extends DecoratedSDKConnector implements SDKConnectorInterface {

  /**
   * The decorated SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnector
   */
  private $innerService;

  /**
   * A logger instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * The service ID.
   *
   * @var string
   */
  public $_serviceId;

  /**
   * Constructs a new SDKConnector.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $inner_service
   *   The decorated SDK connector service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger interface.
   * @param \Drupal\Core\Http\ClientFactory $clientFactory
   *   Http client.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   * @param \Drupal\Core\Extension\InfoParserInterface $infoParser
   *   Info file parser service.
   */
  public function __construct(SDKConnectorInterface $inner_service, LoggerChannelInterface $logger, ClientFactory $clientFactory, KeyRepositoryInterface $key_repository, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, InfoParserInterface $infoParser) {
    $this->innerService = $inner_service;
    $this->logger = $logger;
    parent::__construct($clientFactory, $key_repository, $entity_type_manager, $config_factory, $moduleHandler, $infoParser);
  }

  /**
   * {@inheritdoc}
   */
  public function buildClient(Authentication $authentication, ?string $endpoint = NULL, array $options = []): ClientInterface {
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
    return $this->innerService->buildClient($authentication, $endpoint, [
      Client::CONFIG_RETRY_PLUGIN_CONFIG => [
        'retries' => 5,
        'exception_decider' => $decider,
        'exception_delay' => function (RequestInterface $request, Exception $e, $retries) : int {
          return $retries * 15000000;
        },
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function httpClientConfiguration(): array {
    return $this->innerService->httpClientConfiguration();
  }

}
