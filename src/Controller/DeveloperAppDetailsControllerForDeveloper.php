<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;

/**
 * Lists developer apps of a developer on the UI.
 *
 * @package Drupal\apigee_edge\Controller
 */
class DeveloperAppDetailsControllerForDeveloper extends ControllerBase {

  use DeveloperAppDetailsControllerTrait;

  public function render(UserInterface $user, DeveloperAppInterface $app) {
    $build = [];
    $build['form'] = $this->formBuilder()->getForm('Drupal\apigee_edge\Form\DeveloperAppEditForm', $user, $app);
    return $build;
  }

}
