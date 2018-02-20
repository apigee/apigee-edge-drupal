<?php

namespace Drupal\apigee_edge\Routing;

use Drupal\apigee_edge\Controller\DeveloperAppFieldConfigListController;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -1024];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if (($route = $collection->get('entity.developer_app.field_ui_fields'))) {
      $route->setDefault('_controller', DeveloperAppFieldConfigListController::class . '::listing');
    }
  }

}
