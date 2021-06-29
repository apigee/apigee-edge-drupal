<?php

/**
 * Copyright 2021 Google Inc.
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

namespace Drupal\apigee_edge\Exception;

use Apigee\Edge\Exception\ApiException;

/**
 * This exception is thrown when the developer profile update fails.
 */
final class DeveloperUpdateFailedException extends ApiException implements ApigeeEdgeExceptionInterface {

  /**
   * Email address of the developer.
   *
   * @var string
   */
  private $email;

  /**
   * DeveloperUpdateFailedException constructor.
   *
   * @param string $email
   *   Developer email.
   * @param string $message
   *   Exception message.
   * @param int $code
   *   Error code.
   * @param \Throwable|null $previous
   *   Previous exception.
   */
  public function __construct(string $email, string $message = 'Developer @email profile update failed.', int $code = 0, \Throwable $previous = NULL) {
    $this->email = $email;
    $message = strtr($message, ['@email' => $email]);
    parent::__construct($message, $code, $previous);
  }

  /**
   * Email address of the developer.
   *
   * @return string
   *   Email address.
   */
  public function getEmail(): string {
    return $this->email;
  }

}
