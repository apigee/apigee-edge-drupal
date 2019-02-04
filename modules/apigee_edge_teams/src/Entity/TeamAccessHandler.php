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

namespace Drupal\apigee_edge_teams\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access handler for Team entities.
 */
final class TeamAccessHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The developer storage.
   *
   * @var \Drupal\apigee_edge\Entity\Storage\DeveloperStorageInterface
   */
  private $developerStorage;

  /**
   * TeamAccessHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type);
    $this->developerStorage = $entity_type_manager->getStorage('developer');
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\Core\Access\AccessResult $result */
    $result = parent::checkAccess($entity, $operation, $account);

    if ($result->isNeutral()) {
      $permissions = [
        "$operation any {$entity->getEntityTypeId()}",
      ];
      if ($this->entityType->getAdminPermission()) {
        $permissions[] = $this->entityType->getAdminPermission();
      }
      $result = AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');

      if ($result->isNeutral() && $operation === 'view') {
        if ($account->isAuthenticated()) {
          // Grant access to the user if it is a member of the Team.
          // (Reminder, anonymous user can not be member of a team.
          /** @var \Drupal\apigee_edge\Entity\DeveloperInterface|null $developer */
          $developer = $this->developerStorage->load($account->getEmail());
          if ($developer && in_array($entity->id(), $developer->getCompanies())) {
            $result = AccessResult::allowed();
            // Ensure that access is evaluated again when the team or the
            // developer entity changes.
            $result->addCacheableDependency($entity);
            $result->addCacheableDependency($developer);
          }
        }
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $result = parent::checkCreateAccess($account, $context, $entity_bundle);
    if ($result->isNeutral()) {
      $permissions = [
        "create {$this->entityType->id()}",
      ];
      if ($this->entityType->getAdminPermission()) {
        $permissions[] = $this->entityType->getAdminPermission();
      }

      $result = AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
    }

    return $result;
  }

}
