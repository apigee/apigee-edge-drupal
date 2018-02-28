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

use Drupal\apigee_edge\AuthenticationMethodManager;
use Drupal\apigee_edge\CredentialsStorageManager;
use Drupal\apigee_edge\SDKConnector as OriginalSDKConnector;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\ClientFactory;

/**
 * Service decorator for SDKConnector.
 */
class SDKConnector extends OriginalSDKConnector {

  /**
   * Customer http request header.
   *
   * This tells ApiClientProfiler to profile requests made by the underlying
   * HTTP client.
   *
   * @see \Drupal\apigee_edge_debug\HttpClientMiddleware\ApiClientProfiler
   */
  public const HEADER = 'X-Apigee-Edge-Api-Client-Profiler';

  /**
   * @var \Drupal\apigee_edge\SDKConnector*/
  private $innerService;

  /**
   * SDKConnector constructor.
   *
   * @param \Drupal\Core\Http\ClientFactory $clientFactory
   *   Http Client factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\apigee_edge\CredentialsStorageManager $credentials_storage_plugin_manager
   *   Credential storage plugin manager.
   * @param \Drupal\apigee_edge\AuthenticationMethodManager $authentication_method_plugin_manager
   *   Authentication method plugin manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   * @param \Drupal\Core\Extension\InfoParserInterface $infoParser
   *   Info file parser service.
   */
  public function __construct(ClientFactory $clientFactory, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, CredentialsStorageManager $credentials_storage_plugin_manager, AuthenticationMethodManager $authentication_method_plugin_manager, ModuleHandlerInterface $moduleHandler, InfoParserInterface $infoParser) {
    $config = [
      'headers' => [
        static::HEADER => self::HEADER,
      ],
    ];
    $httpClient = $clientFactory->fromOptions($config);
    parent::__construct($httpClient, $entity_type_manager, $config_factory, $credentials_storage_plugin_manager, $authentication_method_plugin_manager, $moduleHandler, $infoParser);
  }

  /**
   * {@inheritdoc}
   */
  public function __call($method, $args) {
    return call_user_func_array([$this->innerService, $method], $args);
  }

}
