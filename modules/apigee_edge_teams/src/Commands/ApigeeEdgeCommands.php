<?php

/**
 * Copyright 2022 Google Inc.
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

namespace Drupal\apigee_edge_teams\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\apigee_edge_teams\CliServiceInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush 11 command file.
 */
class ApigeeEdgeCommands extends DrushCommands {

  /**
   * The interoperability cli service.
   *
   * @var \Drupal\apigee_edge_teams\CliServiceInterface
   */
  protected $cliService;

  /**
   * ApigeeEdgeCommands constructor.
   *
   * @param \Drupal\apigee_edge_teams\CliServiceInterface $cli_service
   *   The CLI service which allows interoperability.
   */
  public function __construct(CliServiceInterface $cli_service = NULL) {
    parent::__construct();
    $this->cliService = $cli_service;
  }

  /**
   * Team Member synchronization.
   *
   * @command apigee-edge-teams:sync
   *
   * @usage drush apigee-edge-teams:sync
   *   Starts the team member synchronization.
   */
  public function sync() {
    $this->cliService->sync($this->io(), 'dt');
  }

}
