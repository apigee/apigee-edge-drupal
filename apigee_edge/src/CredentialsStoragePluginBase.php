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
  public function getId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }

}
