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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->namespaces = QueryBase::getNamespaces($this);
  }

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    if (array_key_exists($entity_type->getClass(), self::$queryClassmap)) {
      $queryClass = self::$queryClassmap[$entity_type->getClass()];
    }
    else {
      $queryClass = $this->findQueryClassForEntityType($entity_type) ?? Query::class;
      self::$queryClassmap[$entity_type->getClass()] = $queryClass;
    }
    $rc = new \ReflectionClass($queryClass);
    return $rc->newInstance($entity_type, $conjunction, $this->namespaces, $this->entityTypeManager);
  }

  /**
   * Finds the appropriate query class for an entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Entity type.
   *
   * @return null|string
   *   Query class or null if not found.
   */
  protected function findQueryClassForEntityType(EntityTypeInterface $entity_type): ?string {
    foreach ($this->entityClasses($entity_type) as $class) {
      if (($queryClass = $this->findQueryClass($class))) {
        return $queryClass;
      }
    }
    return NULL;
  }

  /**
   * Returns the entity class and its parents.
   *
   * This list is used to look up possible query classes.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Entity type.
   *
   * @return array
   *   List of classes.
   */
  protected function entityClasses(EntityTypeInterface $entity_type): array {
    $classes = [];
    $class = $entity_type->getClass();
    while ($class) {
      $classes[] = $class;
      $class = get_parent_class($class);
    }
    return $classes;
  }

  /**
   * Attempts to find a query class for a given entity class.
   *
   * Try to find entity type specific query class based on the following
   * naming convention. If entity is called "Foo" then a "FooQuery" class
   * must exist under \Drupal\apigee_edge\Entity\Query namespace and it
   * must extend the \Drupal\apigee_edge\Entity\Query\Query class.
   * Otherwise the default \Drupal\apigee_edge\Entity\Query\Query class
   * if being used.
   *
   * @param string $class
   *   Entity class.
   *
   * @return null|string
   *   Query class or null if not found.
   */
  protected function findQueryClass(string $class): ?string {
    $entityClassParts = explode('\\', $class);
    $entityName = array_pop($entityClassParts);
    $localEntityQueryClass = implode('\\', $entityClassParts) . "\\Query\\{$entityName}Query";
    if ($this->isValidQueryClass($localEntityQueryClass)) {
      return $localEntityQueryClass;
    }
    $queryClassParts = explode('\\', Query::class);
    // Remove 'Query' from the end of the FQCN.
    array_pop($queryClassParts);
    $queryClassParts[] = $entityName . 'Query';
    $entityQueryClass = implode('\\', $queryClassParts);
    if ($this->isValidQueryClass($entityQueryClass)) {
      return $entityQueryClass;
    }
    return NULL;
  }

  /**
   * Checks if a proposed query class is valid.
   *
   * @param string $class
   *   Query class.
   *
   * @return bool
   *   Decision.
   */
  protected function isValidQueryClass(string $class): bool {
    return class_exists($class) && in_array(Query::class, class_parents($class));
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
