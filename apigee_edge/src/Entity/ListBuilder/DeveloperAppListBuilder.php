<?php

namespace Drupal\apigee_edge\Entity\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

class DeveloperAppListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
    $build = [];

    return $build + parent::buildRow($entity);
  }

}
