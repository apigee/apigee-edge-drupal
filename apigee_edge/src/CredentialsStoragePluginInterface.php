<?php

namespace Drupal\apigee_edge;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for credentials storage plugins.
 */
interface CredentialsStoragePluginInterface extends PluginInspectionInterface {

  /**
   * Return the ID of the credentials storage plugin.
   *
   * @return string
   */
  public function getId();

  /**
   * Return the name of the credentials storage plugin.
   *
   * @return string
   */
  public function getName();

  public function loadCredentials();

  public function saveCredentials();
}
