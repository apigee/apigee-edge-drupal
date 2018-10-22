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

namespace Drupal\apigee_mock_client;

use Apigee\Edge\Exception\ClientErrorException;
use Drupal\Core\Queue\QueueFactory;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Yaml\Yaml;

class MockHandlerStack extends MockHandler {

  /**
   * Responses that have been loaded from the response file.
   *
   * @var array
   */
  protected $responses;

  /**
   * The twig environment for getting response data.
   *
   * @var \Twig_Environment
   */
  protected $twig;

  /**
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $database_queue;

  /**
   * @param \Twig_Environment $twig
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   */
  public function __construct(\Twig_Environment $twig, QueueFactory $queue_factory) {
    $this->twig = $twig;
    $this->database_queue = $queue_factory->get('apigee_m10n_mock_responses', TRUE);

    parent::__construct();
  }

  /**
   * Queue a response that is in the catalog. Dynamic values can be passed and
   * will be replaced in the response. For example `['foo' => [':bar' => 'baz']]`
   * will load the catalog entry named `foo `and replace `:bar` with "baz" in the
   * body text.
   *
   * @param string|array $response_ids
   *   The response id to queue in one of the following formats:
   *     - `'foo'`
   *     - `['foo', 'bar']` // Queue multiple responses.
   *     - `['foo' => [':bar' => 'baz']]` // w/ replacements for response body.
   *
   * @return $this
   *
   * @throws \Twig_Error_Loader
   * @throws \Twig_Error_Runtime
   * @throws \Twig_Error_Syntax
   */
  public function queueFromResponseFile($response_ids) {
    $org_name = \Drupal::service('apigee_edge.sdk_connector')->getOrganization();

    if (empty($this->responses)) {
      // Get the module path for this module.
      $module_path = \Drupal::moduleHandler()->getModule('apigee_mock_client')->getPath();
      $this->responses = Yaml::parseFile($module_path . '/response_catalog.yml')['responses'];
    }
    // Loop through responses and add each one.
    foreach ((array) $response_ids as $index => $item) {
      // The catalog id should either be the item itself or the keys if an associative array has been passed.
      $id = !is_array($item) ? $item : $index;
      // Body text can have elements replaced in it for certain values. See: http://php.net/strtr
      $context = is_array($item) ? $item : [];
      $context['org_name'] = isset($context['org_name']) ? $context['org_name'] : $org_name;

      // Add the default headers if headers aren't defined in the response catalog.
      $headers = isset($this->responses[$id]['headers']) ? $this->responses[$id]['headers'] : [
        'content-type' => 'application/json;charset=utf-8'
      ];
      // Set the default status code.
      $status_code = !empty($this->responses[$id]['status_code']) ? $this->responses[$id]['status_code'] : 200;
      $status_code = !empty($context['status_code']) ? $context['status_code'] : $status_code;

      // Render the response content.
      $template = str_replace('_', '-', $id) . '.json.twig';
      $content = $this->twig->getLoader()->exists($template) ? $this->twig->render($template, $context) : '';

      // Make replacements inside the response body and append the response.
      $this->append(new Response(
        $status_code,
        $headers,
        $content
      ));
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function __invoke(RequestInterface $request, array $options) {
    try {
      // Grab an item from the database queue and append it to the in-memory queue.
      if ($item = $this->database_queue->claimItem()) {
        parent::append(new Response(
          $item->data['status'],
          $item->data['headers'],
          $item->data['body']
        ));
        $this->database_queue->deleteItem($item);
      }

      return parent::__invoke($request, $options);
    } catch (\Exception $ex) {
      // Fake a client error so the error gets reported during testing. The
      // `apigee_edge_user_presave` is catching errors in a way that this error
      // never gets reported to PHPUnit. This won't work for all use cases.
      //
      // @todo: Find another way to ensure an exception gets registered with PHPUnit.
      throw new ClientErrorException(new Response(500, [
        'Content-Type' => 'application/json',
      ], '{"code": "4242", "message": "'.$ex->getMessage().'"}'), $request, $ex->getMessage(), $ex->getCode(), $ex);
    }
  }

  public function append() {
    foreach (func_get_args() as $value) {
      if ($value instanceof ResponseInterface) {

        // Append items to a database queue so they can be accessed in process isolated environments (functional tests).
        $this->database_queue->createItem([
          'status' => $value->getStatusCode(),
          'headers' => $value->getHeaders(),
          'body' => (string) $value->getBody()
        ]);

      } else {
        throw new \InvalidArgumentException('Expected a response. '
                                            . 'Found ' . \GuzzleHttp\describe_type($value));
      }
    }
  }
}
