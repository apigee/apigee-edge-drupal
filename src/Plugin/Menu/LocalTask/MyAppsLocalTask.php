<?php

namespace Drupal\apigee_edge\Plugin\Menu\LocalTask;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\Request;

class MyAppsLocalTask extends LocalTaskDefault {

  use DeveloperRouteParametersTrait;

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    $label = \Drupal::entityTypeManager()->getDefinition('developer_app')->get('label_plural');
    return new TranslatableMarkup("My @label", [
      '@label' => $label,
    ]);
  }

}
