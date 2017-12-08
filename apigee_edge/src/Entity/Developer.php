<?php

namespace Drupal\apigee_edge\Entity;

use Apigee\Edge\Api\Management\Entity\Developer as EdgeDeveloper;

/**
 * Defines the Developer entity class.
 *
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id = "developer",
 *   label = @Translation("Developer"),
 *   handlers = {
 *     "storage" = "\Drupal\apigee_edge\Entity\Storage\DeveloperStorage",
 *   }
 * )
 */
class Developer extends EdgeDeveloper implements DeveloperInterface {

  use EdgeEntityBaseTrait {
    EdgeEntityBaseTrait::id as private drupalId;
  }

  /**
   * @inheritDoc
   */
  public function id() : ? string {
    $id = parent::id();
    return isset($id) ? $id : NULL;
  }

  public function __construct(array $values = []) {
    parent::__construct($values);
    $this->entityTypeId = 'developer';
  }

}
