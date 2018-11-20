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
use Drupal\Core\TypedData\Plugin\DataType\ItemList;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Thrown when a developer attribute's value is not a valid user field value.
 */
class DeveloperToUserConversationInvalidValueException extends DeveloperToUserConversionException {

  /**
   * The source property on the developer.
   *
   * @var string
   */
  protected $source;

  /**
   * The name of the destination field on the user.
   *
   * @var string
   */
  protected $target;

  /**
   * Constraint violation.
   *
   * @var \Symfony\Component\Validator\ConstraintViolationInterface
   */
  protected $violation;

  /**
   * DeveloperToUserConversationInvalidValueException constructor.
   *
   * @param string $attribute_name
   *   The source property on the developer.
   * @param string $target
   *   Name of the destination field on the user.
   * @param \Symfony\Component\Validator\ConstraintViolationInterface $violation
   *   Constraint violation.
   * @param \Drupal\apigee_edge\Entity\DeveloperInterface $developer
   *   Developer entity.
   * @param string $message
   *   Exception message.
   * @param int $code
   *   Error code.
   * @param \Throwable|null $previous
   *   Previous exception.
   */
  public function __construct(string $attribute_name, string $target, ConstraintViolationInterface $violation, DeveloperInterface $developer, string $message = 'Unable to set "@value" value from "@source" source on "@target" target. @reason', int $code = 0, ?\Throwable $previous = NULL) {
    $this->source = $attribute_name;
    $this->target = $target;
    $this->violation = $violation;
    $message = strtr($message, [
      '@reason' => $violation->getMessage(),
      '@source' => $attribute_name,
      '@target' => $target,
      '@value' => is_object($violation->getInvalidValue()) ? ($violation->getInvalidValue() instanceof ItemList ? var_export($violation->getInvalidValue()->getValue(), TRUE) : $violation->getInvalidValue()->value) : $violation->getInvalidValue(),
    ]);
    parent::__construct($developer, $message, $code, $previous);
  }

  /**
   * The source property on the developer.
   *
   * @return string
   *   The source property on the developer.
   */
  public function getSource(): string {
    return $this->source;
  }

  /**
   * The name of the destination field on the user.
   *
   * @return string
   *   The name of the destination field on the user.
   */
  public function getTarget(): string {
    return $this->target;
  }

  /**
   * Constraint violation.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationInterface
   *   Constraint violation.
   */
  public function getViolation(): ConstraintViolationInterface {
    return $this->violation;
  }

}
