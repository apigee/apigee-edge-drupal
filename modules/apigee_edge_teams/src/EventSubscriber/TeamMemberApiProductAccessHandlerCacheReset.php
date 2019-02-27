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

namespace Drupal\apigee_edge_teams\EventSubscriber;

use Drupal\apigee_edge\Event\AppCredentialAddApiProductEvent;
use Drupal\apigee_edge\Event\AppCredentialCreateEvent;
use Drupal\apigee_edge\Event\AppCredentialDeleteApiProductEvent;
use Drupal\apigee_edge\Event\AppCredentialDeleteEvent;
use Drupal\apigee_edge\Event\AppCredentialGenerateEvent;
use Drupal\apigee_edge_teams\TeamMemberApiProductAccessHandlerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Ensures team member API product access handler's cache gets cleared.
 */
final class TeamMemberApiProductAccessHandlerCacheReset implements EventSubscriberInterface {

  /**
   * The team member api product access handler.
   *
   * @var \Drupal\apigee_edge_teams\TeamMemberApiProductAccessHandlerInterface
   */
  private $teamMemberApiProductAccessHandler;

  /**
   * TeamApiProductAccessCacheReset constructor.
   *
   * @param \Drupal\apigee_edge_teams\TeamMemberApiProductAccessHandlerInterface $team_member_api_product_access_handler
   *   The team member api product access handler.
   */
  public function __construct(TeamMemberApiProductAccessHandlerInterface $team_member_api_product_access_handler) {
    $this->teamMemberApiProductAccessHandler = $team_member_api_product_access_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      AppCredentialCreateEvent::EVENT_NAME => 'clearApiProductCache',
      AppCredentialGenerateEvent::EVENT_NAME => 'clearApiProductCache',
      AppCredentialDeleteEvent::EVENT_NAME => 'clearApiProductCache',
      AppCredentialAddApiProductEvent::EVENT_NAME => 'clearApiProductCache',
      AppCredentialDeleteApiProductEvent::EVENT_NAME => 'clearApiProductCache',
    ];
  }

  /**
   * Clears API product entity access cache.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   Event that triggered this subscriber.
   */
  public function clearApiProductCache(Event $event): void {
    $this->teamMemberApiProductAccessHandler->resetCache();
  }

}
