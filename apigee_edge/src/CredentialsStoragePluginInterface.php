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

  /**
   * Read the saved credentials from the storage unit.
   *
   * @return array
   */
  public function loadCredentials();

  /**
   * Save the credentials in the storage unit.
   *
   * @return string
   */
  public function saveCredentials();

  /**
   * Delete credentials from the storage unit.
   *
   * @return string
   */
  public function deleteCredentials();
}
