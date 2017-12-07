<?php

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Entity\Entity;

/**
 * Defines a base Edge entity class.
 */
abstract class EdgeEntityBase extends Entity {

  /**
   * Creates a Drupal entity from an Edge entity.
   *
   * @param \Apigee\Edge\Entity\EntityInterface $entity
   *   The Edge entity.
   *
   * @return static
   *   The Drupal entity.
   */
  abstract public static function createFromEdgeEntity($entity);

  /**
   * Creates an Edge entity from a Drupal entity.
   *
   * @return \Apigee\Edge\Entity\EntityInterface
   *   The Edge entity.
   */
  abstract public function toEdgeEntity();

}
