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

namespace Drupal\apigee_edge_apidocs;

use Drupal\apigee_edge_apidocs\Entity\ApiDocInterface;

/**
 * Interface ApiDocSpecFetcherInterface.
 */
interface ApiDocSpecFetcherInterface {

  /**
   * Fetch OpenAPI specification file from URL.
   *
   * Takes care of updating an ApiDoc entity with the updated spec file. If
   * "spec_file_source" uses a URL, it will fetch the specified file and put it
   * in the "spec" file field. If it uses a "file", it won't change it.
   *
   * @param \Drupal\apigee_edge_apidocs\Entity\ApiDocInterface $apidoc
   *   The ApiDoc entity.
   * @param bool $save
   *   Boolean indicating if method should save the entity.
   * @param bool $new_revision
   *   Boolean indicating if method should create a new revision when saving
   *   the entity.
   * @param bool $show_messages
   *   Boolean indicating if method should display status messages.
   *
   * @return bool
   *   Returns TRUE if the operation completed without errors.
   */
  public function fetchSpec(ApiDocInterface $apidoc, bool $save = TRUE, bool $new_revision = TRUE, bool $show_messages = TRUE) : bool;
}
