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

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the entity query for Apigee Edge entities.
 *
 * Query loader always tries to use the best Apigee Edge endpoint for retrieving
 * already filtered results from Apigee Edge and only do the necessary filtering
 * in the PHP side. It does it in the getFromStorage() method by filtering
 * conditions to find those that can used directly on Apigee Edge.
 * This process does not work on group conditions (OR, AND) it
 * only supports direct field conditions added to the query. Group conditions
 * always evaluated on the PHP side.
 *
 * @code
 * // This works.
 * $query->condition('developerId', 'XY');
 * // But this does not.
 * $or = $query->orConditionGroup().
 * $or->condition('developerId', 'XY')->condition('developerId', 'YX');
 * $query->condition($or);
 * @endcode
 */
class Query extends QueryBase implements QueryInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a Query object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param string $conjunction
   *   - AND: all the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   * @param array $namespaces
   *   List of potential namespaces of the classes belonging to this query.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface|null $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack|null $request_stack
   *   The request stack.
   * @param \Drupal\Core\Render\RendererInterface|null $renderer
   *   The renderer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $module_handler
   *   The module handler.
   */
  public function __construct(EntityTypeInterface $entity_type, string $conjunction, array $namespaces, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user = NULL, RequestStack $request_stack = NULL, RendererInterface $renderer = NULL, ModuleHandlerInterface $module_handler = NULL) {
    parent::__construct($entity_type, $conjunction, $namespaces);

    if ($current_user === NULL) {
      $current_user = \Drupal::currentUser();
      @trigger_error('Calling ' . __METHOD__ . ' without the $current_user is deprecated in apigee_edge:2.0.9 and is required before apigee_edge:3.0.0. See https://www.drupal.org/node/3338498', E_USER_DEPRECATED);
    }

    if ($request_stack === NULL) {
      $request_stack = \Drupal::requestStack();
      @trigger_error('Calling ' . __METHOD__ . ' without the $request_stack is deprecated in apigee_edge:2.0.9 and is required before apigee_edge:3.0.0. See https://www.drupal.org/node/3338498', E_USER_DEPRECATED);
    }

    if ($renderer === NULL) {
      $renderer = \Drupal::service('renderer');
      @trigger_error('Calling ' . __METHOD__ . ' without the $renderer is deprecated in apigee_edge:2.0.9 and is required before apigee_edge:3.0.0. See https://www.drupal.org/node/3338498', E_USER_DEPRECATED);
    }

    if ($module_handler === NULL) {
      $module_handler = \Drupal::moduleHandler();
      @trigger_error('Calling ' . __METHOD__ . ' without the $module_handler is deprecated in apigee_edge:2.0.9 and is required before apigee_edge:3.0.0. See https://www.drupal.org/node/3338498', E_USER_DEPRECATED);
    }

    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // We have to allow getFromStorage() to remove unnecessary query conditions
    // so we have to run it before compile(). Example: DeveloperAppQuery
    // can load only apps of a specific developer by developerId or email.
    // If it does that by email then the email condition should be removed
    // because developer apps do not have email property only developerId.
    // Basically, DeveloperAppQuery already applies a condition on the returned
    // result because this function gets called.
    $all_records = $this->getFromStorage();

    // Be consistent with \Drupal\Core\Entity\Query\Sql\Query::prepare().
    // Add and fire special entity query tags.
    $this->addTag('entity_query');
    $this->addTag('entity_query_' . $this->entityTypeId);

    if ($this->accessCheck) {
      // We do not just add a tag but ensure that only those Apigee entities
      // are returned that the entity access API grants view access.
      // (Storage level filtering is not available or way too limited.)
      $this->addTag($this->entityTypeId . '_access');

      // Read meta-data from query, if provided.
      $account = $this->getMetaData('account');
      if ($account === NULL) {
        $account = $this->currentUser;
      }

      $cacheability = CacheableMetadata::createFromRenderArray([]);
      $viewable_entity_ids = array_reduce($all_records, static function (array $carry, EntityInterface $entity) use ($cacheability, $account) {
        // Bubble up cacheability information even from a revoked access result.
        $result = $entity->access('view', $account, TRUE);
        $cacheability->addCacheableDependency($result);
        if ($result->isAllowed()) {
          $carry[] = $entity->id();
        }
        return $carry;
      }, []);

      // We deliberately add conditions to the original entity query instead
      // of pre-filtering all records because query conditions are visible
      // in hook_query_TAG_alter() implementations for downstream developers.
      if (empty($viewable_entity_ids)) {
        // Add an always false condition. A persisted entity's primary id
        // cannot be null.
        $this->condition->notExists($this->entityType->getKey('id'));
      }
      else {
        $this->condition->condition($this->entityType->getKey('id'), $viewable_entity_ids, 'IN');
      }
      /** @var \Symfony\Component\HttpFoundation\Request $request */
      $request = $this->requestStack->getCurrentRequest();
      if ($request->isMethodCacheable() && $this->renderer->hasRenderContext()) {
        $build = [];
        $cacheability->applyTo($build);
        $this->renderer->render($build);
      }
    }

    $hooks = ['query'];
    foreach ($this->alterTags as $tag => $value) {
      $hooks[] = 'query_' . $tag;
    }
    $this->moduleHandler->alter($hooks, $this);

    $filter = $this->condition->compile($this);
    $result = array_filter($all_records, $filter);

    if ($this->count) {
      return count($result);
    }

    if ($this->sort) {
      uasort($result, function (EntityInterface $entity0, EntityInterface $entity1): int {
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

    return array_map(static function (EntityInterface $entity): string {
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
   *   Array of property names that should be considered as unique entity ids.
   */
  protected function getEntityIdProperties(): array {
    $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
    /** @var \Drupal\apigee_edge\Entity\EdgeEntityInterface $entity */
    $entity = $storage->create();
    return $entity::uniqueIdProperties();
  }

  /**
   * Loads entities from the entity storage for querying.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of matching entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getFromStorage(): array {
    $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
    // The worst case: load all entities from Apigee Edge.
    $ids = NULL;
    $original_conditions = &$this->condition->conditions();
    $filtered_conditions = [];
    foreach ($original_conditions as $key => $condition) {
      $filtered_conditions[$key] = $condition;
      $id = NULL;
      // Indicates whether we found a single entity id in this condition
      // or not.
      $id_found = FALSE;
      // \Drupal\Core\Entity\EntityStorageBase::buildPropertyQuery() always adds
      // conditions with IN this is the reason why the last part of this
      // condition is needed.
      if (in_array($condition['field'], $this->getEntityIdProperties()) && (in_array($condition['operator'], [NULL, '=']) || ($condition['operator'] === 'IN' && is_array($condition['value']) && count($condition['value']) === 1))) {
        if (is_array($condition['value'])) {
          $id = reset($condition['value']);
          $id_found = TRUE;
        }
        else {
          $id = $condition['value'];
          $id_found = TRUE;
        }
      }

      // We have to handle propertly when a developer probably unintentionally
      // passed an empty value (null, false, "", etc.) as a value of a condition
      // for a primary entity id. In this case we should return empty result
      // immediately because this condition can not be evaluated Apigee Edge
      // and we should not load all entities unnecessarily to get same result
      // after filtered the results in the PHP side.
      if ($id_found) {
        if (empty($id)) {
          return [];
        }
        else {
          $ids = [$id];
          unset($filtered_conditions[$key]);
          // If we found an id field in the query do not look for another
          // because that would not make any sense to query one entity by
          // both id fields. (Where in theory both id field could refer to a
          // different entity.)
          break;
        }
      }
    }
    // Remove conditions that is going to be applied on Apigee Edge
    // (by calling the proper API with the proper parameters).
    // We do not want to apply the same filters on the result in execute()
    // again.
    $original_conditions = $filtered_conditions;

    return $storage->loadMultiple($ids);
  }

}
