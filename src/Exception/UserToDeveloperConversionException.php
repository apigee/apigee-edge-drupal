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

namespace Drupal\apigee_edge\Exception;

use Drupal\user\UserInterface;

/**
 * Base exception class for user to developer conversion errors.
 */
class UserToDeveloperConversionException extends UserDeveloperConversionException {

  /**
   * User object.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * UserToDeveloperConversionException constructor.
   *
   * @param \Drupal\user\UserInterface $user
   *   User object.
   * @param string $message
   *   The Exception message, available replacements: @user (email).
   * @param int|null $code
   *   The Exception code, default is the user id.
   * @param \Throwable|null $previous
   *   The previous throwable used for the exception chaining.
   */
  public function __construct(UserInterface $user, string $message = 'Unable to convert "@user" user to developer.', ?int $code = NULL, ?\Throwable $previous = NULL) {
    $this->user = $user;
    $message = strtr($message, ['@user' => $user->getEmail()]);
    $code = $code ?? $user->id();
    parent::__construct($message, $code, $previous);
  }

  /**
   * Returns the problematic user object.
   *
   * @return \Drupal\user\UserInterface
   *   User object.
   */
  public function getUser(): UserInterface {
    return $this->user;
  }

}
