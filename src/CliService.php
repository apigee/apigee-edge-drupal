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

namespace Drupal\apigee_edge;

use Drupal\apigee_edge\Controller\DeveloperSyncController;
use Drupal\apigee_edge\Command\Util\ApigeeEdgeManagementCliServiceInterface;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * A CLI service which defines all the commands logic and delegates the methods.
 */
class CliService implements CliServiceInterface {

  /**
   * The service that makes calls to the Apigee API.
   *
   * @var \Drupal\apigee_edge\Command\Util\ApigeeEdgeManagementCliServiceInterface
   */
  private $apigeeEdgeManagementCliService;

  /**
   * CliService constructor.
   *
   * @param \Drupal\apigee_edge\Command\Util\ApigeeEdgeManagementCliServiceInterface $apigeeEdgeManagementCliService
   *   The ApigeeEdgeManagementCliService to make calls to Apigee Edge.
   */
  public function __construct(ApigeeEdgeManagementCliServiceInterface $apigeeEdgeManagementCliService) {
    $this->apigeeEdgeManagementCliService = $apigeeEdgeManagementCliService;
  }

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

  /**
   * {@inheritdoc}
   */
  public function createEdgeRoleForDrupal(
    StyleInterface $io,
    callable $t,
    string $org,
    string $email,
    string $password,
    ?string $base_url,
    ?string $role_name,
    ?bool $force
  ) {
    $this->apigeeEdgeManagementCliService->createEdgeRoleForDrupal(
      $io,
      $t,
      $org,
      $email,
      $password,
      $base_url,
      $role_name,
      $force
    );
  }

}
