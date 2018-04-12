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
  protected $entityTypeManager;

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
    $this->entityTypeManager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $filter = $this->condition->compile($this);
    $all_records = $this->getFromStorage();

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

  /**
   * Returns an array of properties that should be considered as entity ids.
   *
   * Usually one entity has one primary id, but in case of Apigee Edge
   * entities one entity could have multiple ids (primary keys).
   * Ex.: Developer => ['email', 'developerId'].
   *
   * @return string[]
   *   Array of property names that should be considered as primary entity ids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getEntityIdProperties() {
    $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
    /** @var \Apigee\Edge\Entity\EntityInterface $entity */
    $entity = $storage->create();
    return [$entity->idProperty()];
  }

  /**
   * Loads entities from the entity storage for querying.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of matching entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getFromStorage() {
    $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
    // The worst case: load all entities from Apigee Edge.
    $ids = NULL;
    foreach ($this->condition->conditions() as $condition) {
      // \Drupal\Core\Entity\EntityStorageBase::buildPropertyQuery() always adds
      // conditions with IN that is why the last part of this condition
      // is needed.
      if (in_array($condition['field'], $this->getEntityIdProperties()) && (in_array($condition['operator'], [NULL, '=']) || ($condition['operator'] === 'IN' && count($condition['value']) === 1))) {
        if (is_array($condition['value'])) {
          $ids = [reset($condition['value'])];
        }
        else {
          $ids = [$condition['value']];
        }
        // If we found an id field in the query do not look for an another
        // because that would not make any sense to query one entity by
        // both id fields. (Where in theory both id field could refer to a
        // different entity.)
        break;
      }
    }
    return $storage->loadMultiple($ids);
  }

}
