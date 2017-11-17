<?php

namespace Drupal\apigee_edge;

use Drupal\Component\Plugin\PluginBase;

/**
 * Defines the CredentialsStoragePluginBase abstract class.
 */
abstract class CredentialsStoragePluginBase extends PluginBase implements CredentialsStoragePluginInterface {

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
