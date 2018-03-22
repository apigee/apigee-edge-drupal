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

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryException;
use Drupal\Core\Entity\Query\QueryFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Factory for creating entity query objects for the Apigee Edge backend.
 */
class QueryFactory implements QueryFactoryInterface, EventSubscriberInterface {

  /**
   * The namespace of this class, the parent class etc.
   *
   * @var string[]
   */
  protected $namespaces;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Stores mapping between entity types and entity query classes.
   *
   * @var string[]
   *   An associative array where keys are the FQCN-s of entity types and values
   *   are the FQCN-s of the entity query classes.
   */
  private static $queryClassmap = [];

  /**
   * Constructs a QueryFactory object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->namespaces = QueryBase::getNamespaces($this);
  }

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    $queryClass = Query::class;
    if (array_key_exists($entity_type->getClass(), self::$queryClassmap)) {
      $queryClass = self::$queryClassmap[$entity_type->getClass()];
    }
    else {
      // Try to find entity type specific query class based on the following
      // naming convention. If entity is called "Foo" then a "FooQuery" class
      // must exist under \Drupal\apigee_edge\Entity\Query namespace and it
      // must extend the \Drupal\apigee_edge\Entity\Query\Query class.
      // Otherwise the default \Drupal\apigee_edge\Entity\Query\Query class
      // if being used.
      $tmp = explode('\\', $entity_type->getClass());
      $entityName = end($tmp);
      $tmp = explode('\\', $queryClass);
      // Remove 'Query' from the end of the FQCN.
      array_pop($tmp);
      $tmp[] = $entityName . 'Query';
      $entityQueryClass = implode('\\', $tmp);
      if (class_exists($entityQueryClass) && in_array($queryClass, class_parents($entityQueryClass))) {
        $queryClass = $entityQueryClass;
      }
      self::$queryClassmap[$entity_type->getClass()] = $queryClass;
    }
    $rc = new \ReflectionClass($queryClass);
    return $rc->newInstance($entity_type, $conjunction, $this->namespaces, $this->entityTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public function getAggregate(EntityTypeInterface $entity_type, $conjunction) {
    throw new QueryException('Aggregation over Apigee Edge entities is not supported.');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [];
  }

}
