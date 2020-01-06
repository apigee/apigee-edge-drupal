<?php

/*
 * Copyright 2020 Google Inc.
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

use Drupal\Core\Http\ClientFactory;
use Drupal\Core\State\StateInterface;
use Apigee\MockClient\GuzzleHttp\MockHandler;
use GuzzleHttp\HandlerStack;

/**
 * Class MockHttpClientFactory.
 */
class MockHttpClientFactory extends ClientFactory {

  /**
   * The handler stack (retained for compatibility).
   *
   * @var \GuzzleHttp\HandlerStack
   */
  protected $stack;

  /**
   * The mock handler stack (Allows us to queue responses).
   *
   * @var \GuzzleHttp\HandlerStack
   */
  protected $mock_stack;

  /**
   * Whether or not integration is currently enabled.
   *
   * @var bool
   */
  protected $integration_enabled;

  /**
   * Constructs a new ClientFactory instance.
   *
   * @param \GuzzleHttp\HandlerStack $stack
   *   The handler stack.
   * @param \Apigee\MockClient\GuzzleHttp\MockHandler $mock_stack
   *   The mock handler stack (Allows us to queue responses).
   * @param \Drupal\Core\State\StateInterface $state
   *   Drupal state service, used to determine whether tests should be run
   *   using the mock handler or against a remote edge instance.
   */
  public function __construct(HandlerStack $stack, MockHandler $mock_stack, StateInterface $state) {
    $this->stack = $stack;
    $this->mock_stack = $mock_stack;

    // Check for the integration enabled environment variable.
    if ($enabled = getenv('APIGEE_INTEGRATION_ENABLE')) {
      $this->integration_enabled = !empty($enabled);
      // Callbacks won't have access to the same environment variables so save
      // the flag to state.
      $state->set('APIGEE_INTEGRATION_ENABLE', $enabled);
    }
    else {
      $this->integration_enabled = !empty($state->get('APIGEE_INTEGRATION_ENABLE', FALSE));
    }

    parent::__construct($stack);
  }

  /**
   * {@inheritdoc}
   */
  public function fromOptions(array $config = []) {
    $config = [
      'handler' => $this->integration_enabled ? $this->stack : $this->mock_stack,
    ];

    return parent::fromOptions($config);
  }

}
