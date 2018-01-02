<?php

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Entity\EntityInterface;
use Apigee\Edge\Api\Management\Entity\DeveloperAppInterface as EdgeDeveloperAppInterface;

interface DeveloperAppInterface extends EdgeDeveloperAppInterface, EntityInterface {

}
