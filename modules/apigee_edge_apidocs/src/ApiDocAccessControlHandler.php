<?php

/**
 * Copyright 2019 Google Inc.
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

namespace Drupal\apigee_edge_apidocs;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the API Doc entity.
 *
 * @see \Drupal\apigee_edge_apidocs\Entity\ApiDoc.
 */
class ApiDocAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $parent_access = parent::checkAccess($entity, $operation, $account);

    if (!$parent_access->isAllowed()) {
      /** @var \Drupal\apigee_edge_apidocs\Entity\ApiDocInterface $entity */
      switch ($operation) {
        case 'view':
          if (!$entity->isPublished()) {
            return $parent_access->orIf(AccessResult::allowedIfHasPermission($account, 'view unpublished apidoc entities'));
          }
          return $parent_access->orIf(AccessResult::allowedIfHasPermission($account, 'view published apidoc entities'));

        case 'update':
          return $parent_access->orIf(AccessResult::allowedIfHasPermission($account, 'edit apidoc entities'));

        case 'delete':
          return $parent_access->orIf(AccessResult::allowedIfHasPermission($account, 'delete apidoc entities'));
      }
    }

    // Unknown operation, no opinion.
    return $parent_access;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add apidoc entities')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'administer apidoc entities'));
  }

}
