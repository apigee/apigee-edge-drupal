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

namespace Drupal\apigee_edge\Entity\Query;

use Apigee\Edge\Api\Management\Controller\AppByOwnerControllerInterface;
use Apigee\Edge\Exception\ApiException;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Base entity query class for developer- and team apps.
 */
abstract class AppQueryBase extends Query {

  /**
   * Returns field names(s) in a condition that contain an app owner criteria.
   *
   * @return array
   *   Array of field name(s) that contain an app owner criteria in a query.
   */
  abstract protected function appOwnerConditionFields() : array;

  /**
   * Returns an app by owner controller.
   *
   * @param string $owner
   *   The owner an of an app.
   *
   * @return \Apigee\Edge\Api\Management\Controller\AppByOwnerControllerInterface
   *   The app by owner controller instance for the owner.
   */
  abstract protected function appByOwnerController(string $owner) : AppByOwnerControllerInterface;

  /**
   * {@inheritdoc}
   */
  protected function getFromStorage(): array {
    /** @var \Drupal\apigee_edge\Entity\Storage\AppStorage $storage */
    $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
    $ids = NULL;
    $app_owner_in_conditions = NULL;
    $app_name = NULL;
    $original_conditions = &$this->condition->conditions();
    $filtered_conditions = [];
    foreach ($original_conditions as $key => $condition) {
      $filtered_conditions[$key] = $condition;
      if (in_array($condition['field'], $this->appOwnerConditionFields()) && in_array($condition['operator'], [NULL, '='])) {
        // Indicates whether we found a single app owner id in this condition
        // or not.
        $app_owner_id_found = FALSE;
        if (!is_array($condition['value'])) {
          $app_owner_in_conditions = $condition['value'];
          $app_owner_id_found = TRUE;
        }
        elseif (is_array($condition['value']) && count($condition['value']) === 1) {
          $app_owner_in_conditions = reset($condition['value']);
          $app_owner_id_found = TRUE;
        }

        if ($app_owner_id_found) {
          // Sanity- and security check. The developer who set an empty value
          // (null, false, '', etc) as the value of an app owner id condition
          // probably made an unintentional mistake. If we would still load all
          // apps in this case that could lead to information
          // disclosure or worse case a security leak.
          if (empty($app_owner_in_conditions)) {
            return [];
          }
          else {
            // We have a valid app owner id that can be passed to Apigee Edge
            // to return its apps.
            unset($filtered_conditions[$key]);
          }
        }
      }
      // TODO Add support to IN conditions (multiple app names) when it
      // becomes necessary.
      elseif ($condition['field'] === 'name' && in_array($condition['operator'], [NULL, '='])) {
        $app_name_found = FALSE;
        if (!is_array($condition['value'])) {
          $app_name = $condition['value'];
          $app_name_found = TRUE;
        }
        elseif (is_array($condition['value']) && count($condition['value']) === 1) {
          $app_name = reset($condition['value']);
          $app_name_found = TRUE;
        }

        if ($app_name_found) {
          // The same as above, the provided condition can not be evaluated
          // on Apigee Edge so let's return immediately.
          if (empty($app_name)) {
            return [];
          }
          else {
            // We have a valid app name that can be passed to Apigee Edge
            // to return its apps.
            unset($filtered_conditions[$key]);
          }
        }
      }
    }
    // Remove conditions that is going to be applied on Apigee Edge
    // (by calling the proper API with the proper parameters).
    // We do not want to apply the same filters on the result in execute()
    // again.
    $original_conditions = $filtered_conditions;

    // Load only one app owner's apps instead of all apps.
    if ($app_owner_in_conditions !== NULL) {
      // Load only one app instead of all apps of an app owner.
      if ($app_name !== NULL) {
        // Try to retrieve the appId from the cache, because if we load the
        // app with that then we can leverage the our entity cache.
        $app_id = $storage->getCachedAppId($app_owner_in_conditions, $app_name);
        if ($app_id) {
          try {
            $entity = $storage->load($app_id);
            // If the app found in the cache then return it, if not then it can
            // mean that the cached app id is outdated (ex.: app had been
            // deleted from Apigee Edge in somewhere else than the Developer
            // Portal). In that case try to load the app by name directly from
            // Apigee Edge.
            if ($entity) {
              return [$entity];
            }
          }
          catch (EntityStorageException $e) {
            // Just catch it and try to load the app by name.
          }
        }

        try {
          /** @var \Apigee\Edge\Api\Management\Entity\AppInterface $entity */
          $entity = $this->appByOwnerController($app_owner_in_conditions)->load($app_name);
          // We have to use the storage because it ensures that next time the
          // app can be found in the cache (and various other things as well).
          return [$storage->load($entity->getAppId())];
        }
        catch (ApiException $e) {
          // App does not exists with name.
        }

        return [];
      }
      else {
        // Get the name of apps that the app owner owns. Apigee Edge only
        // returns the name of the apps therefore the response body should
        // be a lot smaller compared with retrieving all app entity data - maybe
        // unnecessarily if we already have them in cache - and it should be
        // produced and retrieved more quickly.
        $app_names = $this->appByOwnerController($app_owner_in_conditions)->getEntityIds();
        $cached_app_ids = array_map(function ($app_name) use ($storage, $app_owner_in_conditions) {
          return $storage->getCachedAppId($app_owner_in_conditions, $app_name);
        }, $app_names);
        // Remove those null values that indicates an app name could not be
        // found in cache.
        $cached_app_ids = array_filter($cached_app_ids);

        // It seems that we might have all apps in cache that this app owner
        // owns at this moment.
        if (count($app_names) === count($cached_app_ids)) {
          return $storage->loadMultiple($cached_app_ids);
        }

        // It seems we do not have cached app ids for all apps that this
        // app owner owns.
        // We need app ids (UUIDs) first that are only available on app
        // entities therefore we have to load them by using the controller.
        // After we have the app ids we have to let the storage to load
        // entities again, because that ensures that new entities being cached
        // and all hooks and events are being called and trigger (besides
        // other various tasks).
        // (Add static cache to the controller if this still not performs as
        // good as expected.)
        $ids = array_map(function ($entity) {
          /** @var \Drupal\apigee_edge\Entity\AppInterface $entity */
          return $entity->getAppId();
        }, $this->appByOwnerController($app_owner_in_conditions)->getEntities());
        if ($ids) {
          return $storage->loadMultiple($ids);
        }
      }
      // The app owner has no apps, do not call Apigee Edge unnecessarily.
      return [];
    }
    return parent::getFromStorage();
  }

}
