<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Api\Management\Controller\ApiProductController;
use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Drupal\apigee_edge\SDKConnectorInterface;

/**
 * Controller class for API products.
 */
class ApiProductStorage extends EdgeEntityStorageBase implements ApiProductStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getController(SDKConnectorInterface $connector) : EntityCrudOperationsControllerInterface {
    return new ApiProductController($connector->getOrganization(), $connector->getClient());
  }

}
