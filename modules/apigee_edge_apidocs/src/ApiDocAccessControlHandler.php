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
    /** @var \Drupal\apigee_edge_apidocs\Entity\ApiDocInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished apidoc entities')
            ->orIf(AccessResult::allowedIfHasPermission($account, 'administer apidoc entities'));
        }
        return AccessResult::allowedIfHasPermission($account, 'view published apidoc entities')
          ->orIf(AccessResult::allowedIfHasPermission($account, 'administer apidoc entities'));

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit apidoc entities')
          ->orIf(AccessResult::allowedIfHasPermission($account, 'administer apidoc entities'));

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete apidoc entities')
          ->orIf(AccessResult::allowedIfHasPermission($account, 'administer apidoc entities'));
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add apidoc entities')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'administer apidoc entities'));
  }

}
