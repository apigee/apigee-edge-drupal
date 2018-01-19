<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Drupal\apigee_edge\SDKConnectorInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * A storage that supports Edge entity types.
 */
interface EdgeEntityStorageInterface extends EntityStorageInterface {

  /**
   * Returns the controller for the current entity.
   *
   * @param \Drupal\apigee_edge\SDKConnectorInterface $connector
   *   The SDK Connector service.
   *
   * @return \Apigee\Edge\Controller\EntityCrudOperationsControllerInterface
   *   The controller must also implement CpsListingEntityControllerInterface
   *   or NonCpsListingEntityControllerInterface.
   */
  public function getController(SDKConnectorInterface $connector) : EntityCrudOperationsControllerInterface;

}
