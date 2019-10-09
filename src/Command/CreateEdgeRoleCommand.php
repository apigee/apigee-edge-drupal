<?php

/**
 * Copyright 2019 Google Inc.
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

namespace Drupal\apigee_edge\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Developer synchronization command class for Drupal Console.
 *
 * @Drupal\Console\Annotations\DrupalCommand (
 *     extension="apigee_edge",
 *     extensionType="module"
 * )
 */
class CreateEdgeRoleCommand extends CommandBase {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('apigee_edge:role:create')
      ->setDescription($this->trans('commands.apigee_edge.role.create.description'))
      ->setHelp('commands.apigee_edge.role.create.help')
      ->addArgument(
        'org',
        InputArgument::REQUIRED,
        $this->trans('commands.apigee_edge.role.create.arguments.org')
      )
      ->addArgument(
        'email',
        InputArgument::REQUIRED,
        $this->trans('commands.apigee_edge.role.create.arguments.email')
      )
      ->addOption(
        'password',
        'p',
        InputArgument::OPTIONAL,
        $this->trans('commands.apigee_edge.role.create.options.password')
      )
      ->addOption(
        'base-url',
        'b',
        InputArgument::OPTIONAL,
        $this->trans('commands.apigee_edge.role.create.options.base-url')
      )
      ->addOption(
        'role-name',
        'r',
        InputArgument::OPTIONAL,
        $this->trans('commands.apigee_edge.role.create.options.role-name')
      )->addOption(
        'force',
        'f',
        InputOption::VALUE_NONE,
        $this->trans('commands.apigee_edge.role.create.options.force')
      );

  }

  /**
   * {@inheritdoc}
   */
  public function interact(InputInterface $input, OutputInterface $output) {
    $this->setupIo($input, $output);
    $password = $input->getOption('password');
    if (!$password) {
      $password = $this->getIo()->askHidden(
        $this->trans('commands.apigee_edge.role.create.questions.password')
      );
      $input->setOption('password', $password);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute(InputInterface $input, OutputInterface $output) {
    $this->setupIo($input, $output);
    $org = $input->getArgument('org');
    $email = $input->getArgument('email');
    $password = $input->getOption('password');
    $base_url = $input->getOption('base-url');
    $role_name = $input->getOption('role-name');
    $force = $input->getOption('force');

    $this->cliService->createEdgeRoleForDrupal($this->getIo(), 't', $org, $email, $password, $base_url, $role_name, $force);
  }

}
