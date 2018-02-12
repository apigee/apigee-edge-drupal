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
 * A job to create a Drupal user from an Apigee Edge developer.
 */
class CreateUser extends EdgeJob {

  /**
   * The developer's email.
   *
   * @var string
   */
  protected $mail;

  /**
   * {@inheritdoc}
   */
  public function __construct(string $mail) {
    parent::__construct();
    $this->mail = $mail;
  }

  /**
   * {@inheritdoc}
   */
  protected function executeRequest() {
    /** @var \Drupal\apigee_edge\Entity\Developer $developer */
    $developer = Developer::load($this->mail);
    User::create([
      'name' => $developer->getUserName(),
      'mail' => $developer->getEmail(),
      'first_name' => $developer->getFirstName(),
      'last_name' => $developer->getLastName(),
      'status' => $developer->getStatus() === Developer::STATUS_ACTIVE,
      'pass' => user_password(),
    ])->save();
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return t('Copying developer (@mail) to Drupal from Edge.', [
      '@mail' => $this->mail,
    ])->render();
  }

}
