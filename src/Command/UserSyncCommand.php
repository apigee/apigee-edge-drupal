<?php

namespace Drupal\apigee_edge\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * User synchronization command class for Drupal Console.
 *
 * @Drupal\Console\Annotations\DrupalCommand (
 *     extension="apigee_edge",
 *     extensionType="module"
 * )
 */
class UserSyncCommand extends CommandBase {

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
