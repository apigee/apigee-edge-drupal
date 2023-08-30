<?php

/**
 * Copyright 2023 Google Inc.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 */

namespace Drupal\apigee_edge\User;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Error;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler that removes the related developer when a user is deleted.
 *
 * ATTENTION!!! Removing a developer from Apigee is a dangerous operation
 * because it also destroys all API keys the developer pwns. If that is not
 * an intended behavior, use the service decorator pattern to customize
 * this process.
 *
 * @see \apigee_edge_user_delete()
 */
final class RemoveRelatedDeveloperAccountSynchronousPostUserDeleteActionPerformer implements PostUserDeleteActionPerformerInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function __invoke(UserInterface $user): void {
    // Do not try to delete developer of the anonymous user because it does
    // not exist.
    if ($user->isAnonymous()) {
      return;
    }

    try {
      /** @var \Drupal\apigee_edge\Entity\DeveloperInterface|null $developer */
      $developer = $this->entityTypeManager->getStorage('developer')->load($user->getEmail());
      // Sanity check, the developer may not exist in Apigee Edge.
      if ($developer) {
        $developer->delete();
        $this->logger->info('The @developer developer has been deleted as a reaction to removing its user account.', [
          '@developer' => $user->getEmail(),
        ]);
      }
    }
    catch (\Exception $exception) {
      $context = [
        '@developer' => $user->getEmail(),
      ];
      Error::logException($this->logger, $exception, 'The @developer developer could not be deleted as a reaction to removing its user account. @message %function (line %line of %file). <pre>@backtrace_string</pre>', $context);
    }
  }

}
