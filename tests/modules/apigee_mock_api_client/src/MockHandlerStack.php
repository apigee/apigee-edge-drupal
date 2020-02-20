<?php

/*
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

namespace Drupal\apigee_mock_api_client;

use Apigee\MockClient\Generator\TwigSource;
use Apigee\MockClient\GuzzleHttp\MockHandler;
use Apigee\MockClient\MockStorageInterface;
use Apigee\MockClient\ResponseFactoryInterface;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Yaml\Yaml;

/**
 * The mock handler stack.
 *
 * The Mock handler stack overrides the Mock handler so we can add a response
 * factory as well.
 */
class MockHandlerStack extends MockHandler {

  /**
   * Responses that have been loaded from the response file.
   *
   * @var array
   */
  protected $responses = [];

  /**
   * The response factory.
   *
   * @var \Apigee\MockClient\ResponseFactoryInterface
   */
  protected $responseFactory;

  /**
   * The twig environment used in the response generator.
   *
   * @var \Twig_Environment
   */
  protected $twig;

  /**
   * Override the mock handler constructor.
   *
   * @param \Apigee\MockClient\MockStorageInterface $storage
   *   Mock storage.
   * @param \Apigee\MockClient\ResponseFactoryInterface $response_factory
   *   The response factory.
   * @param \Twig_Environment $twig
   *   The twig environment used in the response generator.
   */
  public function __construct(MockStorageInterface $storage, ResponseFactoryInterface $response_factory, \Twig_Environment $twig) {
    parent::__construct($storage);

    $this->responseFactory = $response_factory;
    $this->twig = $twig;
  }

  /**
   * Queue a response that is in the catalog.
   *
   * Dynamic values can be passed and
   * will be replaced in the response.
   *
   * @param string|array $response_ids
   *   The name of the response template to queue (without file extension)
   *   e.g. `get-developer` or `get_developer` @see /tests/response-templates.
   *
   * @return $this
   */
  public function queueMockResponse($response_ids) {
    $org_name = \Drupal::service('apigee_edge.sdk_connector')
      ->getOrganization();

    if (empty($this->responses)) {
      // Get the module path for this module.
      $module_path = \Drupal::moduleHandler()
        ->getModule('apigee_mock_api_client')
        ->getPath();
      $this->responses = Yaml::parseFile($module_path . '/response_catalog.yml')['responses'];
    }
    // Loop through responses and add each one.
    foreach ((array) $response_ids as $index => $item) {
      // The catalog id should either be the item itself or the keys if an
      // associative array has been passed.
      $id = !is_array($item) ? $item : $index;
      // Body text can have elements replaced in it for certain values.
      $context = is_array($item) ? $item : [];
      $context['org_name'] = isset($context['org_name']) ? $context['org_name'] : $org_name;

      // Add the default headers if headers aren't defined in the response
      // catalog.
      $headers = isset($this->responses[$id]['headers']) ? $this->responses[$id]['headers'] : [
        'content-type' => 'application/json;charset=utf-8',
      ];
      // Set the default status code.
      $status_code = !empty($this->responses[$id]['status_code']) ? $this->responses[$id]['status_code'] : 200;
      $status_code = !empty($context['status_code']) ? $context['status_code'] : $status_code;

      if ($this->twig->getLoader()->exists($id)) {
        $this->addResponse($this->responseFactory->generateResponse(new TwigSource(
          $id,
          $context,
          $status_code,
          $headers
        )));
      }
      else {
        $this->addResponse(new Response($status_code, $headers, ''));
      }
    }

    return $this;
  }

}
