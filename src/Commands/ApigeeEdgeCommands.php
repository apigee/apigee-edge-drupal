<?php

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
   *   the Developer Portal and the Edge Management Server.
   */
  public function sync() {
    $this->cliService->sync($this->io(), 'dt');
  }

}
