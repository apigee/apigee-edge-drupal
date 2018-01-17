<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Entity\DeveloperAppInterface;
use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\Core\Controller\ControllerBase;

/**
 * Displays the details of a developer app on the UI.
 *
 * @package Drupal\apigee_edge\Controller
 */
class DeveloperAppDetailsController extends ControllerBase implements DeveloperAppPageTitleInterface {

  use DeveloperAppDetailsControllerTrait;

  /**
   * Renders the details of a developer app for a given user.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperAppInterface $developer_app
   *   The developer app entity.
   *
   * @return array
   *   The render array.
   */
  public function render(DeveloperAppInterface $developer_app): array {
    return $this->getRenderArray($developer_app);
  }

}
