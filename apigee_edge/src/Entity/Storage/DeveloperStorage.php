<?php

namespace Drupal\apigee_edge\Entity\Storage;

use Apigee\Edge\Entity\CpsLimitEntityControllerInterface;


/**
 * Controller class for developers.
 */
class DeveloperStorage extends EdgeEntityStorageBase implements DeveloperStorageInterface {

  protected function getController() : CpsLimitEntityControllerInterface {
    return $this->connector->getDeveloperController();
  }

}
