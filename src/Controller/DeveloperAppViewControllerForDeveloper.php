<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Entity\DeveloperAppPageTitleInterface;
use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Displays the view page of a developer app for a given user on the UI.
 *
 * @package Drupal\apigee_edge\Controller
 */
class DeveloperAppViewControllerForDeveloper extends EntityViewController implements DeveloperAppPageTitleInterface {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $app, $view_mode = 'full') {
    $build = parent::view($app, $view_mode);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageTitle(RouteMatchInterface $routeMatch): string {
    return 'title';
  }

}
