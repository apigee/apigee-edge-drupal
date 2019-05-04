<?php

/**
 * Copyright 2019 Google Inc.
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

namespace Drupal\apigee_edge_apidocs\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks for valid file_link.
 *
 * @Constraint(
 *   id = "ApiDocFileLink",
 *   label = @Translation("Checks for valid file_link.", context = "Validation"),
 *   type = "string"
 * )
 */
class ApiDocFileLinkConstraint extends Constraint {

  /**
   * @var string Message to be shown when it is not a valid link.
   */
  public $notValid = '%value is not a valid link';

}
