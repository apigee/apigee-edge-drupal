<?php

namespace Drupal\apigee_edge;

use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Apigee\Edge\HttpClient\ClientInterface;

/**
 * Defines an interface for SDK controller classes.
 */
interface SDKConnectorInterface {

  /**
   * Gets the organization.
   *
   * @return string
   *   The organization.
   */
  public function getOrganization() : string;

  /**
   * Returns the http client.
   *
   * @return \Apigee\Edge\HttpClient\ClientInterface
   *   The http client.
   */
  public function getClient() : ClientInterface;

  /**
   * Gets the requested controller object.
   *
   * Creates the requested controller object using the stored credentials
   * and the configured authentication method.
   *
   * @return \Apigee\Edge\Controller\EntityCrudOperationsControllerInterface
   *   The controller object.
   */
  public function getControllerByEntity(string $entity_type) : EntityCrudOperationsControllerInterface;

}
