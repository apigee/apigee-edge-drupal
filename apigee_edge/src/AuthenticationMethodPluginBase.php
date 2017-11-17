<?php

namespace Drupal\apigee_edge;

use Drupal\Component\Plugin\PluginBase;

/**
 * Defines the AuthenticationMethodPluginBase abstract class.
 */
abstract class AuthenticationMethodPluginBase extends PluginBase implements AuthenticationMethodPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getId() : string {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() : string {
    return $this->pluginDefinition['name'];
  }

}
