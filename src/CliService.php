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

namespace Drupal\apigee_edge;

use Drupal\apigee_edge\Controller\DeveloperSyncController;
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
    $batch = DeveloperSyncController::getBatch();
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
