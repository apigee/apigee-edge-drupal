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

/**
 * Thrown when authentication key not found with the id.
 */
class AuthenticationKeyNotFoundException extends AuthenticationKeyException {

  /**
   * Id of the authentication key.
   *
   * @var string
   */
  protected $keyId;

  /**
   * AuthenticationKeyNotFoundException constructor.
   *
   * @param string $key_id
   *   Id of the authentication key.
   * @param string $message
   *   Exception message.
   * @param int $code
   *   Error code.
   * @param \Throwable|null $previous
   *   Previous exception.
   */
  public function __construct(string $key_id, string $message = 'Authentication key not found with "@id" id.', int $code = 0, \Throwable $previous = NULL) {
    $this->keyId = $key_id;
    $message = strtr($message, ['@id' => $key_id]);
    parent::__construct($message, $code, $previous);
  }

  /**
   * Returns the id of the authentication key that does not belongs to a Key.
   *
   * @return string
   *   Id of the authentication key.
   */
  public function getKeyId(): string {
    return $this->keyId;
  }

}
