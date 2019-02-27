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
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Default team member API product access handler implementation.
 *
 * Some inspiration and code borrowed from core's EntityAccessControlHandler.
 */
final class TeamMemberApiProductAccessHandler implements TeamMemberApiProductAccessHandlerInterface {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * Stores calculated access check results.
   *
   * @var array
   */
  private $accessCache = [];

  /**
   * The team permission handler.
   *
   * @var \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface
   */
  private $teamPermissionHandler;

  /**
   * The currently logged-in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * The team membership manager service.
   *
   * @var \Drupal\apigee_edge_teams\TeamMembershipManagerInterface
   */
  private $teamMembershipManager;

  /**
   * TeamApiProductAccessHandler constructor.
   *
   * @param \Drupal\apigee_edge_teams\TeamMembershipManagerInterface $team_membership_manager
   *   The team membership manager service.
   * @param \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface $team_permission_handler
   *   The team permission handler.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The currently logged-in user.
   */
  public function __construct(TeamMembershipManagerInterface $team_membership_manager, TeamPermissionHandlerInterface $team_permission_handler, ModuleHandlerInterface $module_handler, AccountInterface $current_user) {
    $this->teamMembershipManager = $team_membership_manager;
    $this->teamPermissionHandler = $team_permission_handler;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function access(ApiProductInterface $api_product, string $operation, TeamInterface $team, AccountInterface $account = NULL, bool $return_as_object = FALSE) {
    if ($account === NULL) {
      $account = $this->currentUser;
    }

    if (($return = $this->getCache($api_product, $operation, $team, $account)) !== NULL) {
      // Cache hit, no work necessary.
      return $return_as_object ? $return : $return->isAllowed();
    }

    if ($account->isAnonymous()) {
      $return = AccessResult::forbidden('Anonymous user can not be member of a team.');
    }
    else {
      try {
        $developer_team_ids = $this->teamMembershipManager->getTeams($account->getEmail());
      }
      catch (\Exception $e) {
        $developer_team_ids = [];
      }

      if (in_array($team->id(), $developer_team_ids)) {
        // We grant access to the entity if both of these conditions are met:
        // - No modules say to deny access.
        // - At least one module says to grant access.
        $access = $this->moduleHandler->invokeAll(
          'apigee_edge_teams_team_api_product_access',
          [$api_product, $operation, $team, $account]
        );

        $return = $this->processAccessHookResults($access);

        // Also execute the default access check except when the access result
        // is already forbidden, as in that case, it can not be anything else.
        if (!$return->isForbidden()) {
          $return = $return->orIf($this->checkAccess($api_product, $operation, $team, $account));
        }
      }
      else {
        $return = AccessResultForbidden::forbidden("{$account->getEmail()} is not member of {$team->id()} team.");
      }
    }

    $this->setCache($return, $api_product, $operation, $team, $account);

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
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The team member for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  private function checkAccess(ApiProductInterface $api_product, string $operation, TeamInterface $team, AccountInterface $account): AccessResultInterface {
    if (!in_array($operation, ['view', 'view label', 'assign'])) {
      return AccessResult::neutral(sprintf('%s is not supported by %s.', $operation, __FUNCTION__));
    }
    $product_visibility = $api_product->getAttributeValue('access') ?? 'public';

    return AccessResult::allowedIf(in_array("api_product_access_{$product_visibility}", $this->teamPermissionHandler->getDeveloperPermissionsByTeam($team, $account)))
      // If team membership changes access must be re-evaluated.
      // @see \Drupal\apigee_edge_teams\TeamMembershipManager
      ->addCacheableDependency($team)
      // If API product visibility changes access must be re-evaluated.
      ->addCacheableDependency($api_product)
      ->addCacheableDependency($account);
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
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The team member for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|null
   *   The cached AccessResult, or NULL if there is no record for the given
   *   API Product, operation, and team and account in the cache.
   */
  protected function getCache(ApiProductInterface $api_product, string $operation, TeamInterface $team, AccountInterface $account): ?AccessResultInterface {
    // Return from cache if a value has been set for it previously.
    if (isset($this->accessCache[$team->id()][$account->id()][$api_product->id()][$operation])) {
      return $this->accessCache[$team->id()][$account->id()][$api_product->id()][$operation];
    }

    return NULL;
  }

  /**
   * Statically caches whether the given user has access.
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
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The team member for which to check access.
   */
  protected function setCache(AccessResultInterface $access, ApiProductInterface $api_product, string $operation, TeamInterface $team, AccountInterface $account): void {
    // Save the given value in the static cache and directly return it.
    $this->accessCache[$team->id()][$account->id()][$api_product->id()][$operation] = $access;
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(): void {
    $this->accessCache = [];
  }

}
