<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Job;

use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Base class for all Apigee Edge communication jobs.
 */
abstract class EdgeJob extends Job {

  /**
   * Request data.
   *
   * @var array
   */
  protected $request;

  /**
   * Response data.
   *
   * @var array
   */
  protected $response;

  /**
   * {@inheritdoc}
   */
  public function execute(): bool {
    $this->executeRequest();
    $journal = $this->getConnector()->getClient()->getJournal();
    $request = $journal->getLastRequest();
    $response = $journal->getLastResponse();

    if (!isset($request) || !isset($response)) {
      return FALSE;
    }

    $this->request = [
      'method' => $request->getMethod(),
      'path' => $request->getUri(),
      'headers' => $request->getHeaders(),
      'body' => $request->getBody()->getContents(),
    ];

    $this->response = [
      'version' => $response->getProtocolVersion(),
      'status' => $response->getStatusCode(),
      'headers' => $response->getHeaders(),
      'body' => $response->getBody()->getContents(),
    ];

    return FALSE;
  }

  /**
   * Returns the SDK connector instance from the global container.
   *
   * The reason why this is not injected, because this class will be serialized,
   * and the service class contains elements that can't be serialized.
   *
   * @return \Drupal\apigee_edge\SDKConnector
   *   The SDK connector service.
   */
  protected function getConnector(): SDKConnectorInterface {
    return \Drupal::service('apigee_edge.sdk_connector');
  }

  /**
   * Executes the request itself.
   */
  abstract protected function executeRequest();

  /**
   * {@inheritdoc}
   */
  public function renderArray(): array {
    // TODO visualize Journal.
    return [];
  }

}
