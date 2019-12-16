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

use Consolidation\AnnotatedCommand\CommandData;
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
    parent::__construct();
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

  /**
   * Create a custom role in an Apigee organization for Drupal usage.
   *
   * Create a custom Apigee role that limits permissions for Drupal connections
   * to the Apigee API.
   *
   * @param string $org
   *   The Apigee Edge org to create the role in.
   * @param string $email
   *   An Apigee user email address with orgadmin role for this org.
   * @param array $options
   *   Drush options for the command.
   *
   * @option password
   *   Password for the Apigee orgadmin user. If not set, you will be prompted
   *   for the password.
   * @option base-url
   *   Base URL to use, defaults to public cloud URL:
   *   https://api.enterprise.apigee.com/v1.
   * @option role-name
   *   The role to create in the Apigee Edge org, defaults to "drupalportal".
   * @option $force
   *   Force running of permissions on a role that already exists, defaults
   *   to throwing an error message if role exists. Note that permissions are
   *   only added, any current permissions not not removed.
   * @usage drush create-edge-role myorg me@example.com
   *   Create "drupalportal" role as orgadmin me@example.com for org myorg.
   * @usage drush create-edge-role myorg me@example.com --role-name=portal
   *   Create role named "portal"
   * @usage drush create-edge-role myorg me@example.com --base-url=https://api.edge.example.com
   *   Create role on private Apigee Edge server "api.edge.example.com".
   * @usage drush create-edge-role myorg me@example.com --force
   *   Update permissions on "drupalportal" role even if role already exists.
   * @command apigee-edge:create-edge-role
   * @aliases create-edge-role
   */
  public function createEdgeRole(
    string $org,
    string $email,
    array $options = [
      'password' => NULL,
      'base-url' => NULL,
      'role-name' => NULL,
      'force' => FALSE,
    ]) {

    // Call the CLI Service.
    $this->cliService->createEdgeRoleForDrupal(
      $this->io(),
      'dt',
      $org,
      $email,
      $options['password'],
      $options['base-url'],
      $options['role-name'],
      $options['force']
    );
  }

  /**
   * Validate function for the createEdge method.
   *
   * @hook validate apigee-edge:create-edge-role
   */
  public function validateCreateEdgeRole(CommandData $commandData) {
    // If the user did not specify a password, then prompt for one.
    $password = $commandData->input()->getOption('password');
    $email = $commandData->input()->getArgument('email');
    if (empty($password)) {
      $password = $this->io()->askHidden(dt('Enter password for :email', [':email' => $email]), function ($value) {
        return $value;
      });
      $commandData->input()->setOption('password', $password);
    }
  }

}
