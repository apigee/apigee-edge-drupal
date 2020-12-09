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

namespace Drupal\apigee_edge_teams\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity\QueryAccess\QueryAccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to query access events for team_invitation.
 */
class TeamInvitationQueryAccessSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * TeamInvitationQueryAccessSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'entity.query_access.team_invitation' => 'onQueryAccess',
    ];
  }

  /**
   * Modifies the access conditions for team_invitation.
   *
   * @param \Drupal\entity\QueryAccess\QueryAccessEvent $event
   *   The event.
   */
  public function onQueryAccess(QueryAccessEvent $event) {
    // Add a condition to check for a valid team.
    // We query team from storage instead of check for a null team field because
    // the team might have been deleted on the remote server.
    $team_ids = array_keys($this->entityTypeManager->getStorage('team')->loadMultiple());
    $event->getConditions()->addCondition('team', $team_ids);
  }

}
