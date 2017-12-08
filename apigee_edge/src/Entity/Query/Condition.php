<?php

namespace Drupal\apigee_edge\Entity\Query;

use Drupal\Core\Entity\Query\ConditionBase;
use Drupal\Core\Entity\Query\ConditionInterface;

class Condition extends ConditionBase implements ConditionInterface {

  /**
   * {@inheritdoc}
   */
  public function exists($field, $langcode = NULL) {
    return $this->condition($field, NULL, 'IS NOT NULL', $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function notExists($field, $langcode = NULL) {
    return $this->condition($field, NULL, 'IS NULL', $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function compile($query) {
  }

}
