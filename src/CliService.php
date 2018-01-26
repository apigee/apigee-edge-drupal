<?php

namespace Drupal\apigee_edge;

use Drupal\apigee_edge\Controller\UserSyncController;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * A CLI service which defines all the commands logic and delegates the methods.
 */
class CliService implements CliServiceInterface {

  /**
   * {@inheritdoc}
   */
  public function sync(StyleInterface $io, callable $t) {
    $io->title($t('Developer - User synchronization'));
    $batch = UserSyncController::getBatch();
    $last_message = '';

    foreach ($batch['operations'] as $operation) {
      $context = [
        'finished' => 0,
      ];

      while ($context['finished'] < 1) {
        call_user_func_array($operation[0], array_merge($operation[1], [&$context]));
        if (isset($context['message']) && $context['message'] !== $last_message) {
          $io->text($t($context['message']));
        }
        $last_message = $context['message'];

        gc_collect_cycles();
      }
    }
  }

}
