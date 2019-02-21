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

namespace Drupal\apigee_edge;

use Drupal\apigee_edge\Entity\DeveloperInterface;
use Drupal\apigee_edge\Structure\DeveloperToUserConversionResult;
use Drupal\apigee_edge\Structure\UserToDeveloperConversionResult;
use Drupal\user\UserInterface;

/**
 * User-developer converter service definition.
 */
interface UserDeveloperConverterInterface {

  /**
   * Developer-user base field mapping.
   *
   * @var string[]
   */
  public const DEVELOPER_PROP_USER_BASE_FIELD_MAP = [
    'userName' => 'name',
    'email' => 'mail',
    'firstName' => 'first_name',
    'lastName' => 'last_name',
  ];

  /**
   * Converts Drupal user entity to a developer entity.
   *
   * Creates a new developer entity if it did not exist for a user or update
   * properties of the existing developer entity.
   *
   * It modifies only those properties that changed.
   *
   * @param \Drupal\user\UserInterface $user
   *   The Drupal user entity.
   *
   * @return \Drupal\apigee_edge\Structure\UserToDeveloperConversionResult
   *   The result of the conversion.
   */
  public function convertUser(UserInterface $user): UserToDeveloperConversionResult;

  /**
   * Converts a developer entity to a Drupal user entity.
   *
   * Creates a new user entity if it did not exist for a developer or update
   * properties of the existing developer entity.
   *
   * It modifies only those properties that changed.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperInterface $developer
   *   The developer entity.
   *
   * @return \Drupal\apigee_edge\Structure\DeveloperToUserConversionResult
   *   The result of the conversion.
   */
  public function convertDeveloper(DeveloperInterface $developer): DeveloperToUserConversionResult;

}
