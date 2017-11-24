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
   *   The ID of the credentials storage plugin.
   */
  public function getId() : string;

  /**
   * Returns the name of the credentials storage plugin.
   *
   * @return string
   *   The name of the credentials storage plugin.
   */
  public function getName() : string;

  /**
   * Checks the requirements of the storage.
   *
   * @return string
   *   Empty string if every requirement is satisfied
   *   else a warning message which describes the problem.
   */
  public function hasRequirements() : string;

  /**
   * Loads the saved credentials from the storage unit.
   *
   * @return CredentialsInterface
   *   The CredentialsInterface which contains the stored API credentials.
   */
  public function loadCredentials() : CredentialsInterface;

  /**
   * Saves the credentials in the storage unit.
   *
   * @param CredentialsInterface $credentials
   *   The credentials object.
   *
   * @return bool
   *   TRUE if saving the credentials was successful else FALSE.
   *
   * @throws CredentialsSaveException
   *   When unable to save credentials.
   */
  public function saveCredentials(CredentialsInterface $credentials);

  /**
   * Deletes the credentials from the storage unit.
   */
  public function deleteCredentials();

}
