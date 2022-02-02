<?php

/**
 * Copyright 2021 Google Inc.
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
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LogLevel;

/**
 * Http client middleware that profiles Apigee Edge API calls.
 */
final class DevelKintApiClientProfiler {

  /**
   * The currently logged-in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private $messenger;

  /**
   * The debug message formatter plugin.
   *
   * @var \Drupal\apigee_edge_debug\Plugin\DebugMessageFormatter\DebugMessageFormatterPluginInterface
   */
  private $formatter;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|null
   */
  private $moduleHandler;

  /**
   * DevelKintApiClientProfiler constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\apigee_edge_debug\DebugMessageFormatterPluginManager $debug_message_formatter_plugin
   *   Debug message formatter plugin manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The currently logged-in user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, DebugMessageFormatterPluginManager $debug_message_formatter_plugin, AccountInterface $currentUser, ModuleHandlerInterface $module_handler, MessengerInterface $messenger) {
    // On module install, this constructor is called earlier than
    // the module's configuration would have been imported to the database.
    // In that case the $formatterPluginId is missing and it causes fatal
    // errors.
    $formatter_plugin_id = $config_factory->get('apigee_edge_debug.settings')->get('formatter');
    if ($formatter_plugin_id) {
      $this->formatter = $debug_message_formatter_plugin->createInstance($formatter_plugin_id);
    }
    $this->currentUser = $currentUser;
    $this->moduleHandler = $module_handler;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function __invoke() {
    return function ($handler) {
      return function (RequestInterface $request, array $options) use ($handler) {
        // If devel kint module is enabled and the user has devel kint permission.
        if ($this->moduleHandler->moduleExists('kint') && $this->currentUser->hasPermission('access kint')) {
          // If the formatter has been initialized yet then do nothing.
          if (!$this->formatter) {
            return $handler($request, $options);
          }
          $formatter = $this->formatter;
          $rest_call = [];
          if (isset($options[RequestOptions::ON_STATS])) {
            $next = $options[RequestOptions::ON_STATS];
          }
          else {
            $next = function (TransferStats $stats) {};
          }
          $options[RequestOptions::ON_STATS] = function (TransferStats $stats) use ($request, $next, $formatter) {
            $this->messenger->addStatus(t('<h3>Edge Calls</h3>'));
            $level = LogLevel::DEBUG;
            // Do not modify the original request object in the subsequent calls.
            $request_clone = clone $request;
            $rest_call['Request'] = $formatter->formatRequest($request_clone);
            if ($stats->hasResponse()) {
              // Do not modify the original response object in the subsequent calls.
              $response_clone = clone $stats->getResponse();
              $rest_call['Response'] = $formatter->formatResponse($response_clone, $request_clone);
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
              $rest_call['Error'] = $error;
            }
            $next($stats);
            $rest_call['Time Elapsed'] = $formatter->formatStats($stats);
            $rest_call['Severity'] = $level ?? '';
            ksm($rest_call);
          };
        }
        return $handler($request, $options);
      };
    };
  }

}
