<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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
   * Tells the editing form if the credentials can be saved.
   *
   * Some storage types might be read-only.
   *
   * @return bool
   *   TRUE if the storage is only readable, else FALSE.
   */
  public function readonly() : bool;

  /**
   * Returns information on additional configuration for this plugin.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Additional configuration information.
   */
  public function helpText() : ? TranslatableMarkup;

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
