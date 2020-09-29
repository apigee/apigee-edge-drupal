<?php

namespace Drupal\apigee_edge\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a secret element.
 *
 * @RenderElement("secret")
 */
class Secret extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'secret',
      '#value' => NULL,
    ];
  }

}
