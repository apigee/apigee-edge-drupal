<?php
/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the 
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY 
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public 
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along 
 * with this program; if not, write to the Free Software Foundation, Inc., 51 
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\EventSubscriber;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registers the 'type' of the 'app' route parameter if 'user' is also in path.
 *
 * The 'developer_app' parameter has already automatically resolved by
 * EntityResolvedManager, but in that case the value of in the path is the app
 * id and not the name of the app.
 */
class DeveloperAppByNameRouteAlterSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RoutingEvents::ALTER][] = ['onRoutingRouteAlterSetType'];
    return $events;
  }

  /**
   * Applies parameter converters to route parameters.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The event to process.
   */
  public function onRoutingRouteAlterSetType(RouteBuildEvent $event) {
    foreach ($event->getRouteCollection() as $route) {
      if (in_array('user', $route->compile()->getPathVariables()) && in_array('app', $route->compile()->getPathVariables())) {
        $route->setOption('parameters', ['app' => ['type' => 'developer_app_by_name']]);
      }
    }
  }

}
