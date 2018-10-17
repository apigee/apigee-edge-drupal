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
 * Thrown when source or destination field on user does not exist.
 */
class UserDeveloperConversionUserFieldDoesNotExistException extends UserDeveloperConversionException {

  /**
   * Name of the problematic field.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * UserDeveloperConversionUserFieldDoesNotExistException constructor.
   *
   * @param string $field_name
   *   Name of the problematic field.
   * @param string $message
   *   The Exception message.
   * @param int|null $code
   *   The error code.
   * @param \Throwable|null $previous
   *   The previous throwable used for the exception chaining.
   */
  public function __construct(string $field_name, string $message = 'Field "@field" does not exist on user anymore.', ?int $code = NULL, ?\Throwable $previous = NULL) {
    $message = strtr($message, ['@field' => $field_name]);
    $this->fieldName = $field_name;
    parent::__construct($message, $code, $previous);
  }

  /**
   * Returns the name of the problematic field.
   *
   * @return string
   *   Name of the problematic field.
   */
  public function getFieldName(): string {
    return $this->fieldName;
  }

}
