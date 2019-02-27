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
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\key\KeyRepositoryInterface;

/**
 * Service decorator for SDKConnector.
 */
class SDKConnector extends OriginalSDKConnector implements SDKConnectorInterface {

  /**
   * Customer http request header.
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
   * @var \Drupal\apigee_edge\SDKConnector
   */
  private $innerService;

  /**
   * Constructs a new SDKConnector.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $inner_service
   *   The decorated SDK connector service.
   * @param \Drupal\Core\Http\ClientFactory $client_factory
   *   Http client.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param \Drupal\Core\Extension\InfoParserInterface $info_parser
   *   Info file parser service.
   */
  public function __construct(SDKConnectorInterface $inner_service, ClientFactory $client_factory, KeyRepositoryInterface $key_repository, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, InfoParserInterface $info_parser) {
    $this->innerService = $inner_service;
    parent::__construct($client_factory, $key_repository, $entity_type_manager, $config_factory, $module_handler, $info_parser);
  }

  /**
   * {@inheritdoc}
   */
  protected function httpClientConfiguration(): array {
    $config = $this->innerService->httpClientConfiguration();
    $config['headers'][static::HEADER] = static::HEADER;
    return $config;
  }

}
