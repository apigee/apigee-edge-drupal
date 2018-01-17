<?php

namespace Drupal\apigee_edge\Entity\Controller;

use Apigee\Edge\Controller\CpsListingEntityControllerInterface;
use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;

/**
 * Extended Developer app controller interface for Drupal.
 *
 * @package Drupal\apigee_edge\Entity\Controller
 */
interface DeveloperAppControllerInterface extends EntityCrudOperationsControllerInterface, CpsListingEntityControllerInterface {

}
