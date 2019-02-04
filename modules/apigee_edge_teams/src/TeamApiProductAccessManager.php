<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge_teams;

use Drupal\apigee_edge\Entity\ApiProductInterface;
use Drupal\apigee_edge_teams\Entity\TeamInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Default team API product access manager implementation.
 *
 * Idea and some code borrowed from core's EntityAccessControlHandler.
 */
final class TeamApiProductAccessManager implements TeamApiProductAccessManagerInterface {

  private const CONFIG_OBJECT_NAME = 'apigee_edge_teams.team_permissions';

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config;

  /**
   * Stores calculated access check results.
   *
   * @var array
   */
  private $accessCache = [];

  /**
   * TeamApiProductAccessManager constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config) {
    $this->moduleHandler = $module_handler;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function access(ApiProductInterface $api_product, string $operation, TeamInterface $team, bool $return_as_object = FALSE) {

    if (($return = $this->getCache($api_product, $operation, $team)) !== NULL) {
      // Cache hit, no work necessary.
      return $return_as_object ? $return : $return->isAllowed();
    }

    // We grant access to the entity if both of these conditions are met:
    // - No modules say to deny access.
    // - At least one module says to grant access.
    $access = $this->moduleHandler->invokeAll('apigee_edge_teams_team_api_product_access', [
      $api_product, $operation, $team,
    ]);

    $return = $this->processAccessHookResults($access);

    // Also execute the default access check except when the access result is
    // already forbidden, as in that case, it can not be anything else.
    if (!$return->isForbidden()) {
      $return = $return->orIf($this->checkAccess($api_product, $operation, $team));
    }
    $this->setCache($return, $api_product, $operation, $team);
    return $return_as_object ? $return : $return->isAllowed();
  }

  /**
   * Performs access checks.
   *
   * @param \Drupal\apigee_edge\Entity\ApiProductInterface $api_product
   *   The API Product entity for which to check access.
   * @param string $operation
   *   The entity operation. Usually one of 'view', 'update', 'create',
   *   'delete' or 'assign".
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  private function checkAccess(ApiProductInterface $api_product, string $operation, TeamInterface $team): AccessResultInterface {
    if (!in_array($operation, ['view', 'view label', 'assign'])) {
      return AccessResult::neutral(sprintf('%s is not supported by %s.', $operation, __FUNCTION__));
    }
    $product_visibility = $api_product->getAttributeValue('access') ?? 'public';
    // If config key does not exist this revokes access to the API product.
    $access_granted = (bool) $this->config->get(static::CONFIG_OBJECT_NAME)->get("api_product_access_{$product_visibility}");

    return AccessResult::allowedIf($access_granted)
      // If team membership changes access must be re-evaluated.
      // @see \Drupal\apigee_edge_teams\TeamMembershipManager
      ->addCacheableDependency($team)
      // If API product visibility changes access must be re-evaluated.
      ->addCacheableDependency($api_product)
      // If config object changes access must be re-evaluated.
      ->addCacheTags(['config:' . static::CONFIG_OBJECT_NAME]);
  }

  /**
   * We grant access to the entity if all conditions are met.
   *
   * Conditions:
   * - No modules say to deny access.
   * - At least one module says to grant access.
   *
   * @param \Drupal\Core\Access\AccessResultInterface[] $access
   *   An array of access results of the fired access hook.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The combined result of the various access checks' results. All their
   *   cacheability metadata is merged as well.
   *
   * @see \Drupal\Core\Access\AccessResultInterface::orIf()
   */
  protected function processAccessHookResults(array $access): AccessResultInterface {
    // No results means no opinion.
    if (empty($access)) {
      return AccessResult::neutral();
    }

    /** @var \Drupal\Core\Access\AccessResultInterface $result */
    $result = array_shift($access);
    foreach ($access as $other) {
      $result = $result->orIf($other);
    }
    return $result;
  }

  /**
   * Tries to retrieve a previously cached access value from the static cache.
   *
   * @param \Drupal\apigee_edge\Entity\ApiProductInterface $api_product
   *   The API Product entity for which to check access.
   * @param string $operation
   *   The entity operation. Usually one of 'view', 'update', 'create',
   *   'delete' or 'assign".
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|null
   *   The cached AccessResult, or NULL if there is no record for the given
   *   API Product, operation, and team in the cache.
   */
  protected function getCache(ApiProductInterface $api_product, string $operation, TeamInterface $team): ?AccessResultInterface {
    // Return from cache if a value has been set for it previously.
    if (isset($this->accessCache[$team->id()][$api_product->id()][$operation])) {
      return $this->accessCache[$team->id()][$api_product->id()][$operation];
    }

    return NULL;
  }

  /**
   * Statically caches whether the given team has access.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The access result.
   * @param \Drupal\apigee_edge\Entity\ApiProductInterface $api_product
   *   The API Product entity for which to check access.
   * @param string $operation
   *   The entity operation. Usually one of 'view', 'update', 'create',
   *   'delete' or 'assign".
   * @param \Drupal\apigee_edge_teams\Entity\TeamInterface $team
   *   The team for which to check access.
   */
  protected function setCache(AccessResultInterface $access, ApiProductInterface $api_product, string $operation, TeamInterface $team): void {
    // Save the given value in the static cache and directly return it.
    $this->accessCache[$team->id()][$api_product->id()][$operation] = $access;
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(): void {
    $this->accessCache = [];
  }

}
