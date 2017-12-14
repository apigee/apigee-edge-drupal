<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Entity\EntityCrudOperationsControllerInterface;
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
   *
   * @return \Apigee\Edge\Entity\EntityCrudOperationsControllerInterface
   *   The controller must also implement CpsListingEntityControllerInterface
   *   or NonCpsListingEntityControllerInterface.
   */
  public function getController(SDKConnectorInterface $connector) : EntityCrudOperationsControllerInterface;
}
