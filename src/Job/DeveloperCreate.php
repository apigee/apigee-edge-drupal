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

use Apigee\Edge\Exception\ClientErrorException;
use Drupal\apigee_edge\Entity\Developer;
use Drupal\apigee_edge\Entity\DeveloperInterface;
use Drupal\apigee_edge\Job;
use Drupal\user\UserInterface;

/**
 * A job to create a developer in Edge.
 */
class DeveloperCreate extends EdgeJob {

  /**
   * The developer to create.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface
   */
  protected $developer;

  /**
   * Whether to fail if a developer already exists.
   *
   * @var bool
   */
  protected $failWhenExists;

  /**
   * {@inheritdoc}
   */
  public function __construct(DeveloperInterface $developer, $fail_when_exists = FALSE) {
    parent::__construct();
    $this->developer = $developer;
    $this->failWhenExists = $fail_when_exists;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    try {
      $this->developer->save();
    }
    catch (ClientErrorException $ex) {
      if ($this->failWhenExists || $ex->getEdgeErrorCode() !== Developer::APIGEE_EDGE_ERROR_CODE_DEVELOPER_ALREADY_EXISTS) {
        throw $ex;
      }
      else {
        $this->recordMessage('Developer already exists.');
      }
    }
  }

  /**
   * Creates a job to create a remote developer for a local user.
   *
   * @param \Drupal\user\UserInterface $account
   *   Local Drupal account.
   *
   * @return \Drupal\apigee_edge\Job|null
   *   The created job or null if properties are missing on the local account.
   */
  public static function createForUser(UserInterface $account) : ? Job {
    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = Developer::createFromDrupalUser($account);

    return new static($developer);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() : string {
    return t('Creating developer for @mail on Edge.', [
      '@mail' => $this->developer->getEmail(),
    ])->render();
  }

}
