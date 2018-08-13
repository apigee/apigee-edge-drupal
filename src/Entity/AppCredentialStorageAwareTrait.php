<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Entity;

/**
 * Allows to easily save, retrieve or clear app's credentials.
 *
 * App credentials are being stored in user's private credential storage for
 * security reasons.
 *
 * @see \Drupal\apigee_edge\KeyValueStore\AppCredentialStorage
 * @see \Drupal\apigee_edge\KeyValueStore\AppCredentialStorageFactory
 */
trait AppCredentialStorageAwareTrait {

  /**
   * Returns the currently available best storage implementation.
   *
   * @return \Drupal\Core\TempStore\SharedTempStoreFactory|\Drupal\Core\TempStore\PrivateTempStoreFactory
   *   The storage object.
   */
  private function getStore() {
    // If there is no open session then private temp storage is not working.
    // This could happen if someone uses the Drupal entity controllers from
    // code (ex.: update hooks, tests, Drush commands, etc.).
    if (\Drupal::requestStack()->getCurrentRequest()->getSession() === NULL) {
      return \Drupal::service('apigee_edge.tempstore.shared.app_credentials');
    }
    return \Drupal::service('apigee_edge.tempstore.private.app_credentials');
  }

  /**
   * Generates a collection id for an app.
   *
   * @param string $owner_id
   *   Developer Id or company app name that owns an app.
   * @param string $app_name
   *   Name of an app! (Not the UUID of the app, because credentials API works
   *   with app name instead of id.)
   *
   * @return string
   *   The collection id.
   */
  private function generateCollectionForApp(string $owner_id, string $app_name): string {
    return "{$owner_id}-{$app_name}";
  }

  /**
   * Retrieve app credentials from a user's private credential storage.
   *
   * @param string $owner_id
   *   Developer Id or company app name that owns an app.
   * @param string $app_name
   *   Name of an app! (Not the UUID of the app, because credentials API works
   *   with app name instead of id.)
   *
   * @return \Apigee\Edge\Api\Management\Entity\AppCredentialInterface[]|null
   *   Array of app credentials from cache or null if no entry found in cache.
   */
  protected function getAppCredentialsFromStorage(string $owner_id, string $app_name) {
    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store = $this->getStore()->get($this->generateCollectionForApp($owner_id, $app_name));
    return $store->get('credentials');
  }

  /**
   * Stores am app's credentials in a user's private credential storage.
   *
   * @param string $owner_id
   *   Developer Id or company app name that owns an app.
   * @param string $app_name
   *   Name of an app! (Not the UUID of the app, because credentials API works
   *   with app name instead of id.)
   * @param \Apigee\Edge\Api\Management\Entity\AppCredentialInterface[]|array $credentials
   *   Array of app credentials.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function saveAppCredentialsToStorage(string $owner_id, string $app_name, array $credentials): void {
    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store = $this->getStore()->get($this->generateCollectionForApp($owner_id, $app_name));
    $store->set('credentials', $credentials);
  }

  /**
   * Removes stored credentials of an app from a user's private cred. storage.
   *
   * We always remove all credentials instead of a specific one, because this
   * way we do not need to track whether all credentials are in the app
   * credential storage or not. Also, there is no way to load one missing
   * credential to the storage if it is missing then loading complete app
   * data from Apigee Edge again. (There is no "list app keys by app" API
   * endpoint.)EntityConvertAwareTrait.
   *
   * @param string $owner_id
   *   Developer Id or company app name that owns an app.
   * @param string $app_name
   *   Name of an app! (Not the UUID of the app, because credentials API works
   *   with app name instead of id.)
   *
   * @return bool
   *   TRUE if the object was deleted or does not exist, FALSE otherwise.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function clearAppCredentialsFromStorage(string $owner_id, string $app_name): bool {
    /** @var \Drupal\Core\TempStore\PrivateTempStore $store */
    $store = $this->getStore()->get($this->generateCollectionForApp($owner_id, $app_name));
    $return = $store->delete('credentials');
    // Shared store's delete method does not return anything.
    // Why these storages do not implement a common interface to ensure their
    // common parts are compatible with each other?
    // @link https://www.drupal.org/project/drupal/issues/2008884
    return $return === NULL ? TRUE : $return;
  }

}
