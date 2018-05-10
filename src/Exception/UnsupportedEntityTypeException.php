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

class UnsupportedEntityTypeException extends \RuntimeException {

  /**
   * UnsupportedEntityTypeException constructor.
   *
   * @param string $entityType
   *   Name of the entity type.
   * @param int $code
   *   Error code.
   * @param \Throwable|null $previous
   *   Previous exception.
   */
  public function __construct(string $entityType, int $code = 0, \Throwable $previous = NULL) {
    parent::__construct("{$entityType} is not an Apigee Edge entity.", $code, $previous);
  }

}
