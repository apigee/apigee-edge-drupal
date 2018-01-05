<?php

namespace Drupal\apigee_edge\Plugin\Menu\LocalTask;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\Request;

class CreateAppLocalTask extends LocalTaskDefault {

  use DeveloperRouteParametersTrait;

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request = NULL) {
    $label = \Drupal::entityTypeManager()->getDefinition('developer_app')->get('label_singular');
    return new TranslatableMarkup("Create @label", [
      '@label' => $label,
    ]);
  }

}
