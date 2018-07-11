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

namespace Drupal\apigee_edge\Commands;

use Drupal\apigee_edge\CliServiceInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush 9 command file.
 */
class ApigeeEdgeCommands extends DrushCommands {

  /**
   * The interoperability cli service.
   *
   * @var \Drupal\apigee_edge\CliServiceInterface
   */
  protected $cliService;

  /**
   * ApigeeEdgeCommands constructor.
   *
   * @param \Drupal\apigee_edge\CliServiceInterface $cli_service
   *   The CLI service which allows interoperability.
   */
  public function __construct(CliServiceInterface $cli_service = NULL) {
    $this->cliService = $cli_service;
  }

  /**
   * Developer synchronization.
   *
   * @command apigee-edge:sync
   *
   * @usage drush apigee-edge:sync
   *   Starts the developer synchronization between
   *   the Developer Portal and the Apigee Edge Management Server.
   */
  public function sync() {
    $this->cliService->sync($this->io(), 'dt');
  }

}
