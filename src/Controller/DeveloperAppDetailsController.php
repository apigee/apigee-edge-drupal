<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Entity\DeveloperApp;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;

class DeveloperAppDetailsController extends ControllerBase {

  public function render(UserInterface $user, DeveloperApp $app) {
    // FIXME
    return [
      '#markup' => $app->getDisplayName(),
    ];
  }

}
