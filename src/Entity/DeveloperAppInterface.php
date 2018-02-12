<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\DeveloperAppInterface as EdgeDeveloperAppInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Defines an interface for developer app entity objects.
 */
interface DeveloperAppInterface extends EdgeDeveloperAppInterface, EntityInterface, EntityOwnerInterface, FieldableEntityInterface, \IteratorAggregate {

}
