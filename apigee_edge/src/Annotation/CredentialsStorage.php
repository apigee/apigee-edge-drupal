<?php

namespace Drupal\apigee_edge\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a credentials storage plugin annotation object.
 *
 * @Annotation
 */
class CredentialsStorage extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the storage plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

}
