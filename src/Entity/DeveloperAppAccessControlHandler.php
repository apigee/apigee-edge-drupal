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

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the developer app entity type.
 */
class DeveloperAppAccessControlHandler extends EdgeEntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, $operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $this->prepareUser($account);

    if ($operation === 'any_analytics' && $account->hasPermission('view any developer app analytics')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }
    if ($operation === 'own_analytics' && $account->hasPermission('view own developer app analytics')) {
      $result = AccessResult::allowed()->cachePerPermissions();
      return $return_as_object ? $result : $result->isAllowed();
    }

    $result = parent::access($entity, $operation, $account, TRUE)->cachePerPermissions();

    return $return_as_object ? $result : $result->isAllowed();
  }

}
