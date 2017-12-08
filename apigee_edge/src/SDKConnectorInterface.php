<?php

namespace Drupal\apigee_edge;

use Apigee\Edge\Api\Management\Controller\DeveloperController;
use Apigee\Edge\HttpClient\Client;

/**
 * Defines an interface for SDK controller classes.
 */
interface SDKConnectorInterface {

  /**
   * Returns the http client.
   *
   * @return \Apigee\Edge\HttpClient\Client
   *   The http client.
   */
  public function getClient() : Client;

  /**
   * Gets the DeveloperController object.
   *
   * Creates a DeveloperController object using the stored credentials
   * and the configured authentication method.
   *
   * @return DeveloperController
   *   The DeveloperController object.
   */
  public function getDeveloperController() : DeveloperController;

}
