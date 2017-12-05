<?php

namespace Drupal\apigee_edge\Entity;

/**
 * Defines the Developer entity class.
 *
 * @EdgeEntityType(
 *   id = "apigee_edge_developer",
 *   label = "@Translation("Developer")",
 *   handlers = {
 *     "storage" = "Drupal\apigee_edge\Entity\Storage\DeveloperStorage",
 *   },
 * )
 */
class Developer extends EdgeEntityBase {

}
