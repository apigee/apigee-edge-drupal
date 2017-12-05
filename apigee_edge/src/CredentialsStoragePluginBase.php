<?php

namespace Drupal\apigee_edge;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the CredentialsStoragePluginBase abstract class.
 */
abstract class CredentialsStoragePluginBase extends PluginBase implements CredentialsStoragePluginInterface {

  /**
   * {@inheritdoc}
   */
  public function hasRequirements() : string {
    return '';
  }

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

  /**
   * {@inheritdoc}
   */
  public function readonly() : bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function helpText() : ? TranslatableMarkup {
    return NULL;
  }

}
