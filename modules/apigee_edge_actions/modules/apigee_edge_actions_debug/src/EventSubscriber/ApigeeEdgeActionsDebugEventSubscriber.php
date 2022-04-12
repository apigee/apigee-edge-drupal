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

namespace Drupal\apigee_edge_actions_debug\EventSubscriber;

use Drupal\apigee_edge_actions\Event\ApigeeEdgeActionsEventInterface;
use Drupal\apigee_edge_actions\Event\EdgeEntityEventEdge;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Implements event subscriber for all apigee_edge_actions events.
 */
class ApigeeEdgeActionsDebugEventSubscriber implements EventSubscriberInterface {

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * ApigeeEdgeActionsDebugEventSubscriber constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(LoggerChannelInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Responds to rules events.
   *
   * @param \Drupal\apigee_edge_actions\Event\EdgeEntityEventEdge $event
   *   The event object.
   * @param string $event_name
   *   The event name.
   */
  public function onRulesEvent(EdgeEntityEventEdge $event, $event_name) {
    // Log the dispatched event.
    if ($event instanceof ApigeeEdgeActionsEventInterface) {
      $this->logger->notice("Event $event_name was dispatched.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Ensure this is called after the container is built.
    if (!\Drupal::hasService('state')) {
      return [];
    }

    $events = [];

    // Register a callback for all registered rules events.
    if ($rules_events = \Drupal::state()->get('rules.registered_events')) {
      foreach ($rules_events as $rules_event) {
        $events[$rules_event][] = ['onRulesEvent', 100];
      }
    }

    return $events;
  }

}
