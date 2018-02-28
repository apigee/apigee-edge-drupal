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

use Apigee\Edge\Api\Management\Controller\DeveloperController;
use Apigee\Edge\Api\Management\Entity\Developer;
use Drupal\Core\Database\Connection;

/**
 * A job that synchronizes developers.
 */
class DeveloperSync extends EdgeJob {

  use JobCreatorTrait;

  /**
   * All Apigee Edge accounts.
   *
   * Format: strtolower(email) => email.
   *
   * @var array
   */
  protected $edgeAccounts = [];

  /**
   * All Drupal accounts.
   *
   * Format: strtolower(mail) => mail.
   *
   * @var array
   */
  protected $drupalAccounts = [];

  /**
   * Filter regexp for the edge developer emails.
   *
   * @var string
   */
  protected $filter = NULL;

  /**
   * DeveloperSync constructor.
   *
   * @param null|string $filter
   *   An optional regexp filter for the edge developer emails.
   */
  public function __construct(?string $filter) {
    parent::__construct();
    $this->filter = $filter;
  }

  /**
   * Returns the database connection service.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection service.
   */
  protected function getConnection() : Connection {
    return \Drupal::service('database');
  }

  /**
   * Loads all users' emails.
   *
   * @return array
   *   Format: strtolower(mail) => mail
   */
  protected function loadUserEmails() : array {
    $mails = $this->getConnection()->query("
      SELECT u.mail
      FROM {users_field_data} u
    ")->fetchCol();

    $accounts = [];
    foreach ($mails as $mail) {
      if (isset($mail)) {
        $accounts[strtolower($mail)] = $mail;
      }
    }

    return $accounts;
  }

  /**
   * Loads all Apigee Edge developers' emails.
   *
   * @return array
   *   Format: strtolower(email) => email
   */
  protected function loadEdgeUserEmails() : array {
    $controller = new DeveloperController($this->getConnector()->getOrganization(), $this->getConnector()->getClient());
    $mails = $controller->getEntityIds();

    $accounts = [];
    foreach ($mails as $mail) {
      if ($this->filter && !preg_match($this->filter, $mail)) {
        continue;
      }
      $accounts[strtolower($mail)] = $mail;
    }

    return $accounts;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    $this->drupalAccounts = $this->loadUserEmails();
    $this->edgeAccounts = $this->loadEdgeUserEmails();
  }

  /**
   * {@inheritdoc}
   */
  public function execute() : bool {
    parent::execute();

    foreach ($this->edgeAccounts as $search => $mail) {
      if (empty($this->drupalAccounts[$search])) {
        $createUserJob = new CreateUser($mail);
        $createUserJob->setTag($this->getTag());
        $this->scheduleJob($createUserJob);
      }
    }

    foreach ($this->drupalAccounts as $search => $mail) {
      if (empty($this->edgeAccounts[$search])) {
        $jobs = new JobList(TRUE);
        /** @var \Drupal\user\Entity\User $account */
        if (!($account = user_load_by_mail($mail))) {
          $this->recordMessage("User for {$mail} not found.");
          continue;
        }

        $createDeveloperJob = DeveloperCreate::createForUser($account);
        if (!$createDeveloperJob) {
          $this->recordMessage(t('Skipping @mail user, because of incomplete data', [
            '@mail' => $mail,
          ])->render());
          continue;
        }
        $jobs->addJob($createDeveloperJob);

        if ($account->isBlocked()) {
          $jobs->addJob(new DeveloperSetStatus($mail, Developer::STATUS_INACTIVE));
        }

        $jobs->setTag($this->getTag());

        $this->scheduleJob($jobs);
      }
    }

    // Reset these, so they won't be saved to the database, taking up space.
    $this->edgeAccounts = [];
    $this->drupalAccounts = [];

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() : string {
    return t('Synchronizing developers and users.')->render();
  }

}
