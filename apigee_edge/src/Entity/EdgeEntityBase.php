<?php

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Entity\Entity;

/**
 * Defines a base Edge entity class.
 */
abstract class EdgeEntityBase extends Entity {

  /**
   * @param \Apigee\Edge\Entity\EntityInterface $entity
   *
   * @return static
   */
  abstract public static function createFromEdgeEntity($entity);

  /**
   * @return \Apigee\Edge\Entity\EntityInterface
   */
  abstract public function toEdgeEntity();

}
