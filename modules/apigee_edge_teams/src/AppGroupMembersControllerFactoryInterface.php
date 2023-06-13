<?php

/**
 * Copyright 2023 Google Inc.
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

namespace Drupal\apigee_edge_teams;

/**
 * Base definition of the appgroup members controller factory service.
 */
interface AppGroupMembersControllerFactoryInterface {

  /**
   * Returns a preconfigured appgroup members controller.
   *
   * @param string $appGroup
   *   Name of a appgroup.
   *
   * @return \Drupal\apigee_edge_teams\AppGroupMembersControllerInterface
   *   The preconfigured appgroup members control of the appgroup.
   */
  public function appGroupMembersController(string $appGroup): AppGroupMembersControllerInterface;

}
