<?php

namespace Drupal\apigee_edge\Command;

use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Annotations\DrupalCommand;

/**
 * User synchronization command class for Drupal Console.
 *
 * @DrupalCommand (
 *     extension="apigee_edge",
 *     extensionType="module"
 * )
 */
class UserSyncCommand extends CommandBase {

  use LoggerAwareTrait;

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

    try {
      $this->cliService->sync($this->getIo(), 't');
    }
    catch (\Exception $exception) {
      $this->getIo()->error($exception->getMessage());
    }
  }

}
