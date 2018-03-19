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

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use GuzzleHttp\TransferStats;
use Http\Message\Formatter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines base class for debug message formatter plugins.
 */
abstract class DebugMessageFormatterPluginBase extends PluginBase implements ContainerFactoryPluginInterface, DebugMessageFormatterPluginInterface {

  /**
   * Whether to masquerade the organization in the request URI or not.
   *
   * @var bool
   */
  protected $masqueradeOrganization;

  /**
   * Whether to remove the authorization header from the request or not.
   *
   * @var bool
   */
  protected $removeAuthorizationHeader;

  /**
   * DebugMessageFormatterPluginBase constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public function __construct(ConfigFactoryInterface $config, array $configuration, string $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->masqueradeOrganization = $config->get('apigee_edge_debug.settings')->get('masquerade_organization');
    $this->removeAuthorizationHeader = $config->get('apigee_edge_debug.settings')->get('remove_authorization_header');
  }

  /**
   * {@inheritdoc}
   */
  public function formatRequest(RequestInterface $request) {
    if ($this->removeAuthorizationHeader) {
      $request = $request->withoutHeader('Authorization');
    }
    if ($this->masqueradeOrganization) {
      $pattern = '/(\/v\d+\/(?:o|organizations))(?:\/)([^\/]+)(?:\/?)(.*)/';
      $path = rtrim(preg_replace($pattern, '$1/***organization***/$3', $request->getUri()
        ->getPath()), '/');
      $request = $request->withUri($request->getUri()->withPath($path));
    }

    return $this->getFormatter()->formatRequest($request);
  }

  /**
   * Returns the wrapped message formatter.
   *
   * @return \Http\Message\Formatter
   *   Message formatter.
   */
  abstract protected function getFormatter(): Formatter;

  /**
   * {@inheritdoc}
   */
  public function formatResponse(ResponseInterface $response) {
    return $this->getFormatter()->formatResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($container->get('config.factory'), $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Utility function that collects and formats times from transfer statistic.
   *
   * @param \GuzzleHttp\TransferStats $stats
   *   Transfer statistic.
   * @param int $precision
   *   Precision of rounding applied on times.
   *
   * @return array
   *   Array of measured times.
   */
  protected function getTimeStatsInSeconds(TransferStats $stats, int $precision = 3) {
    $time_stats = array_filter($stats->getHandlerStats(), function ($key) {
      return preg_match('/_time$/', $key);
    }, ARRAY_FILTER_USE_KEY);
    $time_stats = array_map(function ($stat) use ($precision) {
      return round($stat, $precision) . 's';
    }, $time_stats);

    return $time_stats;
  }

}
