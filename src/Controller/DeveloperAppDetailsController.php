<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;

class DeveloperAppDetailsController extends ControllerBase {

  public function render(UserInterface $user, $app_name) {
    // FIXME
    return [
      '#markup' => $app_name,
    ];
  }

}
