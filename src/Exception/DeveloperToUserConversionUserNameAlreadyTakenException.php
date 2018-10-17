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
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Throw when developer's username is already taken in Drupal.
 */
class DeveloperToUserConversionUserNameAlreadyTakenException extends DeveloperToUserConversationInvalidValueException {

  /**
   * DeveloperToUserConversionUserNameAlreadyTakenException constructor.
   *
   * @param \Drupal\apigee_edge\Entity\DeveloperInterface $developer
   *   Developer entity.
   * @param \Symfony\Component\Validator\ConstraintViolationInterface $violation
   *   Constraint violation.
   * @param string $message
   *   Exception message.
   * @param int $code
   *   Error code.
   * @param \Throwable|null $previous
   *   Previous exception.
   */
  public function __construct(DeveloperInterface $developer, ConstraintViolationInterface $violation, string $message = 'The username "@username" is already taken in Drupal.', int $code = 0, ?\Throwable $previous = NULL) {
    $message = strtr($message, ['@username' => $developer->getUserName()]);
    parent::__construct('username', 'name', $violation, $developer, $message, $code, $previous);
  }

}
