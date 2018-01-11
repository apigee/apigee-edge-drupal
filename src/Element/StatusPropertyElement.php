<?php

namespace Drupal\apigee_edge\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a status property element.
 *
 * @RenderElement("status_property")
 */
class StatusPropertyElement extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#theme' => 'status_property',
      '#value' => '',
      '#pre_render' => [
        [$class, 'preRenderMyElement'],
      ],
    ];
  }

  /**
   * Prepare the render array for the template.
   */
  public static function preRenderMyElement($element) {
    $element['value'] = [
      '#markup' => $element['#value'],
    ];

    return $element;
  }

}
