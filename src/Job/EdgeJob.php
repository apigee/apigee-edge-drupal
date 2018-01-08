<?php

namespace Drupal\apigee_edge\Job;

use Drupal\apigee_edge\Job;
use Drupal\apigee_edge\SDKConnector;

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
  public function execute() : bool {
    $this->executeRequest();
    $journal = $this->getConnector()->getClient()->getJournal();
    $request = $journal->getLastRequest();
    $response = $journal->getLastResponse();

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
  protected function getConnector() : SDKConnector {
    return \Drupal::service('apigee_edge.sdk_connector');
  }

  /**
   * Executes the request itself.
   */
  abstract protected function executeRequest();

  /**
   * {@inheritdoc}
   */
  public function renderArray() : array {
    // TODO visualize Journal.
    return [];
  }

}
