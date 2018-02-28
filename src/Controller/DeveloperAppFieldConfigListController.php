<?php

namespace Drupal\apigee_edge\Controller;

use Drupal\apigee_edge\Form\DeveloperAppBaseFieldConfigForm;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\field_ui\Controller\FieldConfigListController;

class DeveloperAppFieldConfigListController extends FieldConfigListController {

  /**
   * {@inheritdoc}
   */
  public function listing($entity_type_id = NULL, $bundle = NULL, RouteMatchInterface $route_match = NULL) {
    $page = parent::listing($entity_type_id, $bundle, $route_match);

    $page['base_field_config'] = $this->formBuilder()->getForm(DeveloperAppBaseFieldConfigForm::class);

    return $page;
  }

}
