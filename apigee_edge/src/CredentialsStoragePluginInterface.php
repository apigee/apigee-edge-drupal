<?php

namespace Drupal\apigee_edge;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for credentials storage plugins.
 */
interface CredentialsStoragePluginInterface extends PluginInspectionInterface {

  /**
   * Returns the ID of the credentials storage plugin.
   *
   * @return string
   */
  public function getId() : string;

  /**
   * Returns the name of the credentials storage plugin.
   *
   * @return string
   */
  public function getName() : string;

  /**
   * Loads the saved credentials from the storage unit.
   *
   * @return CredentialsInterface
   */
  public function loadCredentials() : CredentialsInterface;

  /**
   * Saves the credentials in the storage unit.
   *
   * @return string
   */
  public function saveCredentials();

  /**
   * Deletes the credentials from the storage unit.
   *
   * @return string
   */
  public function deleteCredentials();
}
