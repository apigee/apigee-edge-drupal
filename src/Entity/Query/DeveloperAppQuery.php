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

use Apigee\Edge\Exception\ApiException;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Defines an entity query class for Apigee Edge developer app entities.
 */
class DeveloperAppQuery extends Query {

  /**
   * {@inheritdoc}
   */
  protected function getFromStorage(): array {
    /** @var \Drupal\apigee_edge\Entity\Storage\DeveloperAppStorage $storage */
    $storage = $this->entityTypeManager->getStorage($this->entityTypeId);
    $ids = NULL;
    $developer_id = NULL;
    $app_name = NULL;
    $developer_id_properties = ['developerId', 'email'];
    $original_conditions = &$this->condition->conditions();
    $filtered_conditions = [];
    foreach ($original_conditions as $key => $condition) {
      $filtered_conditions[$key] = $condition;
      if (in_array($condition['field'], $developer_id_properties) && in_array($condition['operator'], [NULL, '='])) {
        // Indicates whether we found a single developer id in this condition
        // or not.
        $developer_id_found = FALSE;
        if (!is_array($condition['value'])) {
          $developer_id = $condition['value'];
          $developer_id_found = TRUE;
        }
        elseif (is_array($condition['value']) && count($condition['value']) === 1) {
          $developer_id = reset($condition['value']);
          $developer_id_found = TRUE;
        }

        if ($developer_id_found) {
          // Sanity- and security check. The developer who set an empty value
          // (null, false, '', etc) as the value of the developer id probably
          // made an unintentional mistake. If we would still load all developer
          // apps in this case that could lead to information disclosure
          // or worse case a security leak.
          if (empty($developer_id)) {
            return [];
          }
          else {
            // We have a valid developer id that can be passed to Apigee Edge
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

    // Load only one developer's apps instead of all apps.
    if ($developer_id !== NULL) {
      /** @var \Drupal\apigee_edge\Entity\Controller\DeveloperAppController $controller */
      $controller = $storage->getController(\Drupal::service('apigee_edge.sdk_connector'));
      // Load only one app instead of all apps of a developer.
      if ($app_name !== NULL) {
        // Try to retrieve the appId from the cache, because if load the
        // developer app with that then we can leverage the our entity cache.
        $app_id = $storage->getCachedAppId($developer_id, $app_name);
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
          /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
          $entity = $controller->loadByAppName($developer_id, $app_name);
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
        // Get the name of apps that the developer owns. Apigee Edge only
        // returns the name of the apps therefore the response body should
        // be smaller compared with retrieving all app entity data - maybe
        // unnecessarily if we already have them in cache - and it should be
        // produced and retrieved more quickly.
        $appNames = $controller->getEntityIdsByDeveloper($developer_id);
        $cachedAppIds = array_map(function ($appName) use ($storage, $developer_id) {
          return $storage->getCachedAppId($developer_id, $appName);
        }, $appNames);
        // Remove those null values that indicates an app name could not be
        // found in cache.
        $cachedAppIds = array_filter($cachedAppIds);

        // It seems that we might have all apps in cache that this developer
        // owns at this moment.
        if (count($appNames) === count($cachedAppIds)) {
          return $storage->loadMultiple($cachedAppIds);
        }

        // It seems we do not have cached app ids for all apps that this
        // developer owns.
        // We need developer app ids first that are only available on app
        // entities therefore we have to load them by using the controller.
        // But after we have the app ids we have to let the storage to load
        // entities again, because that ensures that new entities being cached
        // and all hooks and events are being called and trigger (besides
        // other various tasks).
        // (Add static cache to the controller if this still not performs good
        // enough.)
        $ids = array_map(function ($entity) {
          /** @var \Drupal\apigee_edge\Entity\DeveloperApp $entity */
          return $entity->getAppId();
        }, $controller->getEntitiesByDeveloper($developer_id));
        if ($ids) {
          return $storage->loadMultiple($ids);
        }
      }
      // The developer has no apps, do not call Apigee Edge unnecessarily.
      return [];
    }
    return parent::getFromStorage();
  }

}
