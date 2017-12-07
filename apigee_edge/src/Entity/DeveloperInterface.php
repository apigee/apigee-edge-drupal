<?php

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Entity\EntityInterface;
use \Apigee\Edge\Api\Management\Entity\DeveloperInterface as EdgeDeveloperInterface;

/**
 * Defines an interface for developer entity objects.
 */
interface DeveloperInterface extends EdgeDeveloperInterface, EntityInterface {

}
