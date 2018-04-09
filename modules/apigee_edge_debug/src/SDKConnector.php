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
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\key\KeyRepositoryInterface;
use Http\Adapter\Guzzle6\Client as GuzzleClientAdapter;

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
   * The inner SDK connector service.
   *
   * @var \Drupal\apigee_edge\SDKConnector
   */
  private $innerService;

  /**
   * SDKConnector constructor.
   *
   * @param \Drupal\Core\Http\ClientFactory $clientFactory
   *   Http Client factory service.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   * @param \Drupal\Core\Extension\InfoParserInterface $infoParser
   *   Info file parser service.
   */
  public function __construct(ClientFactory $clientFactory, KeyRepositoryInterface $key_repository, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $moduleHandler, InfoParserInterface $infoParser) {
    parent::__construct($clientFactory, $key_repository, $entity_type_manager, $config_factory, $moduleHandler, $infoParser);
    $config = [
      'headers' => [
        static::HEADER => static::HEADER,
      ],
    ];
    $config = NestedArray::mergeDeep($this->getHttpClientConfiguration($config_factory), $config);
    $this->httpClient = new GuzzleClientAdapter($clientFactory->fromOptions($config));
  }

  /**
   * {@inheritdoc}
   */
  public function __call($method, $args) {
    return call_user_func_array([$this->innerService, $method], $args);
  }

}
