<?php

namespace Drupal\apigee_edge\Plugin\Menu\LocalTask;

use Drupal\apigee_edge\Entity\Developer;
use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\user\Entity\User;

class CreateAppLocalTask extends LocalTaskDefault {

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match) {
    $parameters = [];
    if (($user = $route_match->getParameter('user'))) {
      /** @var User $user */
      /** @var Developer $developer */
      if (is_string($user)) {
        $user = User::load($user);
      }
      $developer = Developer::load($user->getEmail());
      $parameters['developer'] = $developer->uuid();
      $parameters['user'] = $user->id();
    }

    return $parameters;
  }

}
