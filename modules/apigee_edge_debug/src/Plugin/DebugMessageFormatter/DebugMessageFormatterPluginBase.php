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
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\TransferStats;
use Http\Message\Formatter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class for debug message formatter plugins.
 */
abstract class DebugMessageFormatterPluginBase extends PluginBase implements ContainerFactoryPluginInterface, DebugMessageFormatterPluginInterface {

  /**
   * Whether to mask the organization in the request URI or not.
   *
   * @var bool
   */
  protected $maskOrganization;

  /**
   * Whether to remove the authorization header from the request or not.
   *
   * @var bool
   */
  protected $removeCredentials;

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
    $this->maskOrganization = $config->get('apigee_edge_debug.settings')->get('mask_organization');
    $this->removeCredentials = $config->get('apigee_edge_debug.settings')->get('remove_credentials');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($container->get('config.factory'), $configuration, $plugin_id, $plugin_definition);
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
  public function formatRequest(RequestInterface $request): string {
    // Do not modify the original request object.
    if ($this->removeCredentials) {
      $request = $request->withoutHeader('Authorization');
      if ($request->getMethod() === 'POST' && $request->getUri()->getPath() === '/oauth/token') {
        $body = (string) $request->getBody();
        $body = preg_replace('/(.*refresh_token=)([^\&]+)(.*)/', '$1***refresh-token***$3', $body);
        $body = preg_replace('/(.*mfa_token=)([^\&]+)(.*)/', '$1***mfa-token***$3', $body);
        $body = preg_replace('/(.*username=)([^\&]+)(.*)/', '$1***username***$3', $body);
        $body = preg_replace('/(.*password=)([^\&]+)(.*)/', '$1***password***$3', $body);
        $request = $request->withBody(Utils::streamFor($body));
      }
    }
    if ($this->maskOrganization) {
      $pattern = '/(\/v\d+\/(?:mint\/)?(?:o|organizations))(?:\/)([^\/]+)(?:\/?)(.*)/';
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
  public function formatResponse(ResponseInterface $response, RequestInterface $request): string {
    if ($this->removeCredentials) {
      $request = $request->withoutHeader('Authorization');
      $masks = [
        'consumerKey' => '***consumer-key***',
        'consumerSecret' => '***consumer-secret***',
      ];
      $json = json_decode((string) $response->getBody(), TRUE);
      if (json_last_error() === JSON_ERROR_NONE) {
        array_walk_recursive($json, function (&$value, $key) use ($masks) {
          if (isset($masks[$key])) {
            $value = $masks[$key];
          }
        });
        $response = $response->withBody(Utils::streamFor(json_encode((object) $json, JSON_PRETTY_PRINT)));
      }

      if ($request->getMethod() === 'POST' && $request->getUri()->getPath() === '/oauth/token') {
        $json = json_decode((string) $response->getBody(), TRUE);
        if (json_last_error() === JSON_ERROR_NONE) {
          if (isset($json['access_token'])) {
            $json['access_token'] = '***access-token***';
          }
          if (isset($json['refresh_token'])) {
            $json['refresh_token'] = '***refresh-token***';
          }
          $response = $response->withBody(Utils::streamFor(json_encode((object) $json)));
        }
      }
    }
    return $this->getFormatter()->formatResponseForRequest($response, $request);
  }

  /**
   * {@inheritdoc}
   */
  public function formatStats(TransferStats $stats): string {
    return var_export($this->getTimeStatsInSeconds($stats), TRUE);
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
  protected function getTimeStatsInSeconds(TransferStats $stats, int $precision = 3): array {
    $time_stats = array_filter($stats->getHandlerStats(), function ($key) {
      return preg_match('/_time$/', $key);
    }, ARRAY_FILTER_USE_KEY);

    return array_map(function ($stat) use ($precision) {
      return round($stat, $precision) . 's';
    }, $time_stats);
  }

}
