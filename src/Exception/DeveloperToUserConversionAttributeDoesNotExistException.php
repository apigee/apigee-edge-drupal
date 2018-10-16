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

use Drupal\apigee_edge\Entity\DeveloperInterface;

/**
 * Thrown when source attribute does not exist in developer.
 */
class DeveloperToUserConversionAttributeDoesNotExistException extends DeveloperToUserConversionException {

  /**
   * Attribute name.
   *
   * @var string
   */
  protected $attributeName;

  /**
   * DeveloperToUserConversionAttributeDoesNotExistException constructor.
   *
   * @param string $attribute_name
   *   Name of the attribute.
   * @param \Drupal\apigee_edge\Entity\DeveloperInterface $developer
   *   Developer object.
   * @param string $message
   *   The Exception message.
   * @param int $code
   *   The error code.
   * @param \Throwable|null $previous
   *   The previous throwable used for the exception chaining.
   */
  public function __construct(string $attribute_name, DeveloperInterface $developer, string $message = '"@attribute" attribute does not exist in "@developer" developer.', int $code = 0, ?\Throwable $previous = NULL) {
    $this->attributeName = $attribute_name;
    $message = strtr($message, ['@attribute' => $attribute_name]);
    parent::__construct($developer, $message, $code, $previous);
  }

  /**
   * Returns the name of the problematic attribute.
   *
   * @return string
   *   Attribute name.
   */
  public function getAttributeName(): string {
    return $this->attributeName;
  }

}
