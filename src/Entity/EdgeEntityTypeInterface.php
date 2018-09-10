<?php

/**
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides an interface for an Apigee Edge entity type and its metadata.
 */
interface EdgeEntityTypeInterface extends EntityTypeInterface {

  /**
   * Returns the fully-qualified class name of the query class for this entity.
   *
   * @return string
   *   The FQCN of the query class.
   */
  public function getQueryClass(): string;

}
