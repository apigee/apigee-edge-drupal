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

namespace Drupal\apigee_edge\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Developer synchronization command class for Drupal Console.
 *
 * @Drupal\Console\Annotations\DrupalCommand (
 *     extension="apigee_edge",
 *     extensionType="module"
 * )
 * 
 * @deprecated in 3.0.3 and will be removed.
 *
 * @see https://github.com/apigee/apigee-edge-drupal/issues/984
 */
class DeveloperSyncCommand extends CommandBase {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('apigee_edge:sync')
      ->setDescription($this->trans('commands.apigee_edge.sync.description'));
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->setupIo($input, $output);
    $this->cliService->sync($this->getIo(), 't');
  }

}
