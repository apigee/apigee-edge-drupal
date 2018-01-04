<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\ApiProductInterface as EdgeApiProductInterface;
use Drupal\Core\Entity\EntityInterface;

interface ApiProductInterface extends EdgeApiProductInterface, EntityInterface {

}
