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

namespace Drupal\apigee_edge\Job;

use Drupal\apigee_edge\Entity\Developer;

/**
 * A job to create a developer on Apigee Edge.
 */
class DeveloperCreate extends EdgeJob {

  /**
   * The Drupal user's email.
   *
   * @var string
   */
  protected $email;

  /**
   * {@inheritdoc}
   */
  public function __construct(string $email) {
    parent::__construct();
    $this->email = $email;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    /** @var \Drupal\user\UserInterface $user */
    $user = user_load_by_mail($this->email);
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
    $developer = Developer::createFromDrupalUser($user, $this);

    try {
      $developer->save();
    }
    catch (\Exception $exception) {
      $message = 'Skipping creating %email developer: %message';
      $context = [
        '%email' => $this->email,
        '%message' => (string) $exception->getMessage(),
        'link' => $user->toLink(t('View user'))->toString(),
      ];
      \Drupal::logger('apigee_edge_sync')->error($message, $context);
      $this->recordMessage(t('Skipping creating %email developer: %message', $context)->render());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Copying user (@email) to Apigee Edge from Drupal.', [
      '@email' => $this->email,
    ])->render();
  }

}
