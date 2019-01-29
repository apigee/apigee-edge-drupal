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
use Drupal\apigee_edge_teams\TeamApiProductAccessManagerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Ensures that Team API product access manager's cache gets cleared.
 */
final class TeamApiProductAccessManagerCacheReset implements EventSubscriberInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $teamApiProductAccessManager;

  /**
   * TeamApiProductAccessCacheReset constructor.
   *
   * @param \Drupal\apigee_edge_teams\TeamApiProductAccessManagerInterface $team_api_product_access_manager
   *   The entity type manager service.
   */
  public function __construct(TeamApiProductAccessManagerInterface $team_api_product_access_manager) {
    $this->teamApiProductAccessManager = $team_api_product_access_manager;
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
    $this->teamApiProductAccessManager->resetCache();
  }

}
