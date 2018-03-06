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

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\TypedData\TypedData;

/**
 * Definition of Apigee Edge developer id field for User entity.
 */
class ApigeeEdgeDeveloperIdField extends TypedData {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = Developer::load($this->parent->getValue()->getEmail());
    return $developer ? $developer->getDeveloperId() : NULL;
  }

  /**
   * We had to add this method because it is expected to exist.
   *
   * @param string $langcode
   *   Language id.
   *
   * @link https://www.drupal.org/project/drupal/issues/2950450
   */
  public function setLangcode(string $langcode) {
  }

}
