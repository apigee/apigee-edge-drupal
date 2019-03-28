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
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the API Doc entity.
 *
 * @see \Drupal\apigee_edge_apidocs\Entity\ApiDoc.
 */
class ApiDocAccessControlHandler extends EntityAccessControlHandler {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type) {
    parent::__construct($entity_type);

    $this->entityTypeManager = \Drupal::entityTypeManager();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $parent_access = parent::checkAccess($entity, $operation, $account);

    if (!$parent_access->isAllowed()) {
      /** @var \Drupal\apigee_edge_apidocs\Entity\ApiDocInterface $entity */

      // Access control for revisions.
      if (!$entity->isDefaultRevision()) {
        return $this->checkAccessRevisions($entity, $operation, $account);
      }

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
   * Additional access control for revisions.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to check access.
   * @param string $operation
   *   The entity operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function checkAccessRevisions(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $entity_storage */
    $entity_storage = $this->entityTypeManager->getStorage($this->entityTypeId);

    // Must have access to the same operation on the default revision.
    $default_revision = $entity_storage->load($entity->id());
    $entity_access_default = $default_revision->access($operation, $account);
    if (!$entity_access_default) {
      return AccessResult::forbidden();
    }

    $map = [
      'view' => "view all {$this->entityTypeId} revisions",
      'update' => "revert all {$this->entityTypeId} revisions",
      'delete' => "delete all {$this->entityTypeId} revisions",
    ];
    $bundle = $entity->bundle();
    $type_map = [
      'view' => "view {$this->entityTypeId} $bundle revisions",
      'update' => "revert {$this->entityTypeId} $bundle revisions",
      'delete' => "delete {$this->entityTypeId} $bundle revisions",
    ];

    if (!$entity || !isset($map[$operation]) || !isset($type_map[$operation])) {
      // If there was no entity to check against, or the $op was not one of the
      // supported ones, we return access denied.
      return AccessResult::forbidden();
    }

    $admin_permission = $this->entityType->getAdminPermission();

    // Perform basic permission checks first.
    if ($account->hasPermission($map[$operation]) ||
      $account->hasPermission($type_map[$operation]) ||
      ($admin_permission && $account->hasPermission($admin_permission))) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add apidoc entities')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'administer apidoc entities'));
  }

}
