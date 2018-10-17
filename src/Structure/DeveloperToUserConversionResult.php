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

namespace Drupal\apigee_edge\Structure;

use Drupal\user\UserInterface;

/**
 * Contains the result of a developer to Drupal user conversion.
 */
final class DeveloperToUserConversionResult extends UserDeveloperConversionResult {

  /**
   * The result of the conversion.
   *
   * @var \Drupal\user\UserInterface
   */
  private $user;

  /**
   * DeveloperToUserConversionResult constructor.
   *
   * @param \Drupal\user\UserInterface $user
   *   The result of the conversion.
   * @param int $successfully_appliedchanges
   *   Number of successfully applied _necessary_ changes.
   *   (It should not contains redundant changes, ex.: when the property value
   *   has not changed.)
   * @param \Drupal\apigee_edge\Exception\UserDeveloperConversionException[] $problems
   *   Problems occurred meanwhile the conversion (ex.: field validation errors,
   *   etc.)
   */
  public function __construct(UserInterface $user, int $successfully_appliedchanges, array $problems = []) {
    $this->user = $user;
    parent::__construct($successfully_appliedchanges, $problems);
  }

  /**
   * The created Drupal user from a developer.
   *
   * @return \Drupal\user\UserInterface
   *   User object.
   */
  public function getUser(): UserInterface {
    return $this->user;
  }

}
