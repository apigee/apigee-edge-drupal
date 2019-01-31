<?php

/**
 * @file
 * Contains apidoc.page.inc.
 *
 * Page callback for API Doc entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for API Doc templates.
 *
 * Default template: apidoc.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_apidoc(array &$variables) {
  // Fetch ApiDoc Entity Object.
  $apidoc = $variables['elements']['#apidoc'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
