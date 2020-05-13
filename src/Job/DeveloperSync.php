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
use Drupal\user\Entity\User;

/**
 * A job that synchronizes Apigee Edge developers and Drupal users.
 */
class DeveloperSync extends EdgeJob {

  use JobCreatorTrait;

  /**
   * All Apigee Edge developers indexed by their emails.
   *
   * Format: mb_strtolower(email) => Developer.
   *
   * @var \Drupal\apigee_edge\Entity\DeveloperInterface[]
   *
   * @see https://www.drupal.org/project/drupal/issues/2490294
   */
  protected $edgeDevelopers = [];

  /**
   * All Drupal users indexed by their emails.
   *
   * Format: mb_strtolower(mail) => User.
   *
   * @var \Drupal\user\UserInterface[]
   *
   * @see https://www.drupal.org/project/drupal/issues/2490294
   */
  protected $drupalUsers = [];

  /**
   * Filter regexp for the Apigee Edge developer emails.
   *
   * @var string
   */
  protected $filter = NULL;

  /**
   * DeveloperSync constructor.
   *
   * @param null|string $filter
   *   An optional regexp filter for the Apigee Edge developer emails.
   */
  public function __construct(?string $filter) {
    parent::__construct();
    $this->filter = $filter;
  }

  /**
   * Loads all Drupal users indexed my their emails.
   *
   * @return \Drupal\user\UserInterface[]
   *   Format: mb_strtolower(mail) => User
   *
   * @see https://www.drupal.org/project/drupal/issues/2490294
   */
  protected function loadUsers(): array {
    $users = [];
    /** @var \Drupal\user\UserInterface $user */
    foreach (User::loadMultiple() as $user) {
      $email = $user->getEmail();
      if (isset($email)) {
        if ($this->filter && !preg_match($this->filter, $email)) {
          continue;
        }
        else {
          $users[mb_strtolower($email)] = $user;
        }
      }
    }

    return $users;
  }

  /**
   * Loads all Apigee Edge developers indexed my their emails.
   *
   * @return \Drupal\apigee_edge\Entity\DeveloperInterface[]
   *   Format: mb_strtolower(email) => Developer
   *
   * @see https://www.drupal.org/project/drupal/issues/2490294
   */
  protected function loadDevelopers(): array {
    // Reset developer cache, because the developer may be edited on Apigee
    // Edge.
    \Drupal::entityTypeManager()->getStorage('developer')->resetCache();
    $developers = [];
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
    foreach (Developer::loadMultiple() as $developer) {
      $email = $developer->getEmail();
      if ($this->filter && !preg_match($this->filter, $email)) {
        continue;
      }
      else {
        $developers[mb_strtolower($email)] = $developer;
      }
    }

    return $developers;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    $this->drupalUsers = $this->loadUsers();
    $this->edgeDevelopers = $this->loadDevelopers();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): bool {
    parent::execute();

    // Update Apigee Edge developers and Drupal users if needed.
    $identical_entities = array_intersect_key($this->edgeDevelopers, $this->drupalUsers);
    foreach ($identical_entities as $clean_email => $entity) {
      /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
      $developer = $this->edgeDevelopers[$clean_email];
      /** @var \Drupal\user\UserInterface $user */
      $user = $this->drupalUsers[$clean_email];

      $last_modified_delta = $developer->getLastModifiedAt()->getTimestamp() - $user->getChangedTime();
      // Update Drupal user because the Apigee Edge developer is the most
      // recent.
      if ($last_modified_delta >= 0) {
        $update_user_job = new UserUpdate($user->getEmail());
        $update_user_job->setTag($this->getTag());
        $this->scheduleJob($update_user_job);
      }
      // Update Apigee Edge developer because the Drupal user is the most
      // recent.
      elseif ($last_modified_delta < 0) {
        $update_developer_job = new DeveloperUpdate($developer->getEmail());
        $update_developer_job->setTag($this->getTag());
        $this->scheduleJob($update_developer_job);
      }
    }

    // Create missing Drupal users.
    foreach ($this->edgeDevelopers as $clean_email => $developer) {
      if (empty($this->drupalUsers[$clean_email])) {
        $create_user_job = new UserCreate($developer->getEmail());
        $create_user_job->setTag($this->getTag());
        $this->scheduleJob($create_user_job);
      }
    }

    // Create missing Apigee Edge developers.
    foreach ($this->drupalUsers as $clean_email => $user) {
      if (empty($this->edgeDevelopers[$clean_email])) {
        $create_developer_job = new DeveloperCreate($user->getEmail());
        $create_developer_job->setTag($this->getTag());
        $this->scheduleJob($create_developer_job);
      }
    }

    // Reset these, so they won't be saved to the database, taking up space.
    $this->edgeDevelopers = [];
    $this->drupalUsers = [];

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Synchronizing Apigee Edge developers and Drupal users.')->render();
  }

}
