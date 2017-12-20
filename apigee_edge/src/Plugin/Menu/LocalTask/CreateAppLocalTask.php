<?php

namespace Drupal\apigee_edge\Plugin\Menu\LocalTask;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Routing\RouteMatchInterface;

class CreateAppLocalTask extends LocalTaskDefault {

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $parameters = [];
    $user = $route_match->getParameter('user');
    /** @var Developer $developer */
    $developer = Developer::load($user->getEmail());
    $parameters['developer'] = $developer->uuid();

    return $parameters;
  }

}
