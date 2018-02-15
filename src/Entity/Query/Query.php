<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Entity\Query;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Defines the entity query for Apigee Edge entities.
 */
class Query extends QueryBase implements QueryInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $manager;

  /**
   * Constructs a Query object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   * @param array $namespaces
   *   List of potential namespaces of the classes belonging to this query.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, string $conjunction, array $namespaces, EntityTypeManagerInterface $manager) {
    parent::__construct($entity_type, $conjunction, $namespaces);
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $storage = $this->manager->getStorage($this->entityTypeId);
    $filter = $this->condition->compile($this);
    $all_records = $storage->loadMultiple();

    $result = array_filter($all_records, $filter);
    if ($this->count) {
      return count($result);
    }

    if ($this->sort) {
      uasort($result, function (EntityInterface $entity0, EntityInterface $entity1) : int {
        foreach ($this->sort as $sort) {
          $value0 = Condition::getProperty($entity0, $sort['field']);
          $value1 = Condition::getProperty($entity1, $sort['field']);

          $cmp = $value0 <=> $value1;
          if ($cmp === 0) {
            continue;
          }
          if ($sort['direction'] === 'DESC') {
            $cmp *= -1;
          }

          return $cmp;
        }

        return 0;
      });
    }

    $this->initializePager();

    if ($this->range) {
      $result = array_slice($result, $this->range['start'], $this->range['length']);
    }

    return array_map(function (EntityInterface $entity) : string {
      return (string) $entity->id();
    }, $result);
  }

}
