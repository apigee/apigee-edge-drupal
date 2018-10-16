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
 * Base exception class for developer to user conversion errors.
 */
class DeveloperToUserConversionException extends UserDeveloperConversionException {

  /**
   * Developer object.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * DeveloperToUserConversionException constructor.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperInterface $developer
   *   Developer object.
   * @param string $message
   *   The Exception message.
   * @param int $code
   *   The error code.
   * @param \Throwable|null $previous
   *   The previous throwable used for the exception chaining.
   */
  public function __construct(DeveloperInterface $developer, string $message = 'Unable to convert "@developer" developer to user.', int $code = 0, ?\Throwable $previous = NULL) {
    $this->developer = $developer;
    $message = strtr($message, ['@developer' => $developer->getEmail()]);
    parent::__construct($message, $code, $previous);
  }

  /**
   * Returns the problematic developer.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperInterface
   *   Developer object.
   */
  public function getDeveloper(): DeveloperInterface {
    return $this->developer;
  }

}
