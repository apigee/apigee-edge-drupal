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

namespace Drupal\apigee_edge\Entity\Controller;

use Apigee\Edge\Controller\CpsListingEntityControllerInterface;
use Apigee\Edge\Controller\EntityCrudOperationsControllerInterface;
use Apigee\Edge\Entity\EntityInterface;

/**
 * Extended Developer app controller interface for Drupal.
 */
interface DeveloperAppControllerInterface extends
    EntityCrudOperationsControllerInterface,
    CpsListingEntityControllerInterface,
    DrupalEntityControllerInterface {

  /**
   * Loads a developer app by its name.
   *
   * DeveloperId is also required, because app name is only unique per
   * developer.
   *
   * @param string $developerId
   *   UUID of a developer.
   * @param string $appName
   *   Name of app owned by a developer.
   *
   * @return \Apigee\Edge\Entity\EntityInterface|null
   *   The developer app or null if does not exist.
   */
  public function loadByAppName(string $developerId, string $appName) : EntityInterface;

  /**
   * Loads developer apps by developer.
   *
   * @param string $developerId
   *   UUID or email of a developer.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperApp[]
   *   Array of developer apps of a developer.
   */
  public function getEntitiesByDeveloper(string $developerId) : array;

  /**
   * Gets developer app _names_ by developer.
   *
   * The API endpoint returns the name of the apps instead of the app ids
   * which are only unique together with the developer id.
   *
   * @param string $developerId
   *   UUID or email of a developer.
   *
   * @return string[]
   *   Array of developer app _names_.
   *
   * @link https://apidocs.apigee.com/management/apis/get/organizations/%7Borg_name%7D/developers/%7Bdeveloper_email_or_id%7D/apps
   */
  public function getEntityIdsByDeveloper(string $developerId) : array;

}
