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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301,
 * USA.
 */

namespace Drupal\apigee_edge\Plugin\Validation\Constraint;

use Drupal\apigee_edge\Entity\Developer;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that whether a user's email address is already taken on Edge.
 */
class DeveloperEmailUniqueValidator extends ConstraintValidator {

  /**
   * Stores email addresses that should not be validated.
   *
   * @var array
   */
  private static $whitelist = [];

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    if (!$item = $items->first()) {
      return;
    }
    if (in_array($item->value, static::$whitelist)) {
      return;
    }
    $developer = Developer::load($item->value);
    if ($developer) {
      $this->context->addViolation($constraint->message, [
        '%email' => $item->value,
      ]);
    }
  }

  /**
   * Whitelist email address for validation.
   *
   * @param string $email
   *   Email address to whitelist.
   */
  public static function whitelist(string $email) {
    static::$whitelist[] = $email;
  }

}
