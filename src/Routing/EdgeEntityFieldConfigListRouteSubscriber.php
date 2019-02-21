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

namespace Drupal\apigee_edge\Routing;

use Drupal\apigee_edge\Controller\EdgeEntityFieldConfigListController;
use Drupal\apigee_edge\Entity\EdgeEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Replaces the controller on Apigee Edge entities' field config listing UI.
 */
final class EdgeEntityFieldConfigListRouteSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * AppFieldConfigListUiRouteSubscriber constructor.
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
    $events = [];
    $events[RoutingEvents::ALTER] = ['alterRoutes', -1024];
    return $events;
  }

  /**
   * Alters Apigee Edge entity related field config listing UI routes.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The route build event.
   */
  public function alterRoutes(RouteBuildEvent $event) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if (in_array(EdgeEntityInterface::class, class_implements($entity_type->getOriginalClass())) && ($route = $event->getRouteCollection()->get("entity.{$entity_type->id()}.field_ui_fields"))) {
        $route->setDefault('_controller', EdgeEntityFieldConfigListController::class . '::listing');
      }
    }
  }

}
