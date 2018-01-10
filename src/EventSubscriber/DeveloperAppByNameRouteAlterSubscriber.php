<?php

namespace Drupal\apigee_edge\EventSubscriber;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registers the 'type' of the 'app' route parameter if 'user' is also in path.
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
