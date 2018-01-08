<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\DeveloperAppInterface as EdgeDeveloperAppInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\user\EntityOwnerInterface;

interface DeveloperAppInterface extends EdgeDeveloperAppInterface, EntityInterface, EntityOwnerInterface {

}
