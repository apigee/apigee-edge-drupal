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

namespace Drupal\apigee_edge_teams\Entity\Controller;

/**
 * Interface TeamAppControllerFactoryInterface.
 */
interface TeamAppControllerFactoryInterface {

  /**
   * Returns a preconfigured team app controller.
   *
   * @param string $team
   *   Name of a team.
   *
   * @return \Drupal\apigee_edge_teams\Entity\Controller\TeamAppControllerInterface
   *   Team app controller.
   */
  public function teamAppController(string $team): TeamAppControllerInterface;

}
