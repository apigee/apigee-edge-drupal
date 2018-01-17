<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;

/**
 * Displays the details of a developer app for a given user on the UI.
 *
 * @package Drupal\apigee_edge\Controller
 */
class DeveloperAppDetailsControllerForDeveloper extends ControllerBase implements DeveloperAppPageTitleInterface {

  use DeveloperAppDetailsControllerTrait;

  /**
   * Renders the details of a developer app for a given user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $app
   *   The developer app entity.
   *
   * @return array
   *   The render array.
   */
  public function render(UserInterface $user, DeveloperAppInterface $app): array {
    return $this->getRenderArray($app);
  }

}
