<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\DeveloperInterface as EdgeDeveloperInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Defines an interface for developer entity objects.
 */
interface DeveloperInterface extends EdgeDeveloperInterface, EntityInterface, EntityOwnerInterface {

  /**
   * Sets the original email address of the developer.
   *
   * @param null|string $originalEmail
   *   The original email address.
   *
   * @internal
   */
  public function setOriginalEmail(string $originalEmail);

}
