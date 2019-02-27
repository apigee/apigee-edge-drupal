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

namespace Drupal\apigee_edge_debug\HttpClientMiddleware;

use Drupal\apigee_edge_debug\DebugMessageFormatterPluginManager;
use Drupal\apigee_edge_debug\SDKConnector;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Http client middleware that profiles Apigee Edge API calls.
 */
class ApiClientProfiler {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * The debug message formatter plugin.
   *
   * @var \Drupal\apigee_edge_debug\Plugin\DebugMessageFormatter\DebugMessageFormatterPluginInterface
   */
  private $formatter;

  /**
   * The format of the log entry.
   *
   * @var string
   */
  private $logFormat;

  /**
   * ApiClientProfiler constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\apigee_edge_debug\DebugMessageFormatterPluginManager $debug_message_formatter_plugin
   *   Debug message formatter plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger, DebugMessageFormatterPluginManager $debug_message_formatter_plugin) {
    // On module install, this constructor is called earlier than
    // the module's configuration would have been imported to the database.
    // In that case the $formatterPluginId is missing and it causes fatal
    // errors.
    $formatter_plugin_id = $config_factory->get('apigee_edge_debug.settings')->get('formatter');
    if ($formatter_plugin_id) {
      $this->formatter = $debug_message_formatter_plugin->createInstance($formatter_plugin_id);
    }
    $this->logFormat = $config_factory->get('apigee_edge_debug.settings')->get('log_message_format');
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function __invoke() {
    // If the formatter has been initialized yet then do nothing.
    if (!$this->formatter) {
      return function ($handler) {
        return function (RequestInterface $request, array $options) use ($handler) {
          return $handler($request, $options);
        };
      };
    }
    return function ($handler) {
      return function (RequestInterface $request, array $options) use ($handler) {

        // If this request already has an on_stats callback do not override
        // it. Store it and call it after ours.
        if (isset($options[RequestOptions::ON_STATS])) {
          $next = $options[RequestOptions::ON_STATS];
        }
        else {
          $next = function (TransferStats $stats) {};
        }

        $logger = $this->logger;
        $formatter = $this->formatter;
        $log_format = $this->logFormat;

        $options[RequestOptions::ON_STATS] = function (TransferStats $stats) use ($request, $next, $logger, $formatter, $log_format) {
          // Do not modify the original request object in the subsequent calls.
          $request_clone = clone $request;
          $level = LogLevel::DEBUG;
          // Do not log this request if it has not been made by the Apigee Edge
          // SDK connector.
          if (!$request_clone->hasHeader(SDKConnector::HEADER)) {
            return;
          }
          $context = [
            'request_formatted' => $formatter->formatRequest($request_clone),
            'stats' => $formatter->formatStats($stats),
          ];
          if ($stats->hasResponse()) {
            // Do not modify the original response object in the subsequent
            // calls.
            $response_clone = clone $stats->getResponse();
            $context['response_formatted'] = $formatter->formatResponse($response_clone, $request_clone);
            if ($stats->getResponse()->getStatusCode() >= 400) {
              $level = LogLevel::WARNING;
            }
          }
          else {
            $level = LogLevel::ERROR;
            $error = $stats->getHandlerErrorData();
            if (is_object($error)) {
              if (method_exists($error, '__toString')) {
                $error = (string) $error;
              }
              else {
                $error = json_encode($error);
              }
            }
            $context['error'] = $error;
          }
          $logger->log($level, $log_format, $context);
          $next($stats);
        };

        return $handler($request, $options);
      };
    };
  }

}
