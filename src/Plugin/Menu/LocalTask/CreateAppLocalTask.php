<?php

namespace Drupal\apigee_edge\Plugin\Menu\LocalTask;

use Drupal\Core\Menu\LocalTaskDefault;
use Symfony\Component\HttpFoundation\Request;

class CreateAppLocalTask extends LocalTaskDefault {

  use DeveloperRouteParametersTrait;

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    return apigee_edge_create_app_title();
  }

}
