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

namespace Drupal\apigee_edge_teams_invitation_test\EventSubscriber;

use Drupal\apigee_edge_teams\Event\TeamInvitationEventInterface;
use Drupal\apigee_edge_teams\Event\TeamInvitationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines a subscriber for team_invitation events.
 */
class TeamInvitationSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TeamInvitationEvents::CREATED][] = 'onCreated';
    $events[TeamInvitationEvents::DECLINED][] = 'onDeclined';
    $events[TeamInvitationEvents::ACCEPTED][] = 'onAccepted';
    return $events;
  }

  /**
   * Callback for on created event.
   *
   * @param \Drupal\apigee_edge_teams\Event\TeamInvitationEventInterface $event
   *   The event.
   */
  public function onCreated(TeamInvitationEventInterface $event) {
    $team_invitation = $event->getTeamInvitation();
    $team_invitation->setLabel("CREATED")
      ->save();
  }

  /**
   * Callback for on declined event.
   *
   * @param \Drupal\apigee_edge_teams\Event\TeamInvitationEventInterface $event
   *   The event.
   */
  public function onDeclined(TeamInvitationEventInterface $event) {
    $team_invitation = $event->getTeamInvitation();
    $team_invitation->setLabel("DECLINED")
      ->save();
  }

  /**
   * Callback for on accepted event.
   *
   * @param \Drupal\apigee_edge_teams\Event\TeamInvitationEventInterface $event
   *   The event.
   */
  public function onAccepted(TeamInvitationEventInterface $event) {
    $team_invitation = $event->getTeamInvitation();
    $team_invitation->setLabel("ACCEPTED")
      ->save();
  }

}
