<?php

/**
 * Copyright 2020 Google Inc.
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

use Drupal\apigee_edge_teams\TeamPermissionHandlerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\EntityAccessControlHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access controller handler for team_invitation.
 */
final class TeamInvitationAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The team permissions handler.
   *
   * @var \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface
   */
  protected $teamPermissionHandler;

  /**
   * TeamInvitationAccessControlHandler constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\apigee_edge_teams\TeamPermissionHandlerInterface $team_permission_handler
   *   The team permissions handler.
   */
  public function __construct(EntityTypeInterface $entity_type, TeamPermissionHandlerInterface $team_permission_handler) {
    parent::__construct($entity_type);
    $this->teamPermissionHandler = $team_permission_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('apigee_edge_teams.team_permissions')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\apigee_edge_teams\Entity\TeamInvitation $entity */
    $account = $this->prepareUser($account);

    // Check if team exists.
    if (!$entity->getTeam()) {
      return AccessResult::forbidden('Team does not exist.')
        ->addCacheableDependency($entity);
    }

    // Access is allowed if the user can accept invitation and the invitation
    // is pending.
    if ($entity->isPending() && $operation === 'accept') {
      return AccessResult::allowedIf($account->getEmail() == $entity->getRecipient())
        ->andIf(AccessResult::allowedIfHasPermissions($account, ['accept own team invitation', 'accept any team invitation'], 'OR'))
        ->addCacheableDependency($entity)
        ->cachePerUser();
    }

    // Access is allowed if the user can decline invitation and the invitation
    // is pending.
    if ($entity->isPending() && $operation === 'decline') {
      return AccessResult::allowedIf($account->getEmail() == $entity->getRecipient())
        ->andIf(AccessResult::allowedIfHasPermissions($account, ['decline own team invitation', 'decline any team invitation'], 'OR'))
        ->addCacheableDependency($entity)
        ->cachePerUser();
    }

    // Access allowed if user can administer team invitations for team or if
    // user has permissions to administer all team invitations.
    // Note: This is handled at team level permissions.
    if ($operation === 'delete' || $operation === "resend") {
      return AccessResult::allowedIf(in_array('team_manage_members', $this->teamPermissionHandler->getDeveloperPermissionsByTeam($entity->getTeam(), $account)))
        ->orIf(AccessResult::allowedIfHasPermissions($account, ['administer team', 'manage team members'], 'OR'))
        ->addCacheableDependency($entity)
        ->cachePerUser();
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
