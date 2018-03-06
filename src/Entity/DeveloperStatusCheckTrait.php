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

namespace Drupal\apigee_edge\Entity;

use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Provides a trait for checking developer status.
 */
trait DeveloperStatusCheckTrait {

  /**
   * Checks the status of the given user's Edge developer.
   *
   * Checks the status of the developer assigned to the given Drupal user
   * and notifies the current user if the developer's status is inactive.
   *
   * @param int|null $uid
   *   The user ID.
   */
  private function checkDeveloperStatus(?int $uid) {
    if ($uid === NULL) {
      return;
    }

    $user = User::load($uid);
    /** @var \Drupal\apigee_edge\Entity\DeveloperInterface $developer */
    $developer = Developer::load($user->getEmail());
    if (!isset($developer) || $developer->getStatus() === Developer::STATUS_INACTIVE) {
      // Displays different warning message for admin users.
      $message = $user->id() === \Drupal::currentUser()->id()
        ? t('Your developer account has inactive status so you will not be able to use your credentials until your account is enabled. Please contact the Developer Portal support for further assistance.')
        : t('The developer account of <a href=":url">@username</a> has inactive status so this user has invalid credentials until the account is enabled.', [
          ':url' => Url::fromRoute('entity.user.edit_form', ['user' => $uid])->toString(),
          '@username' => $user->getAccountName(),
        ]);
      \Drupal::messenger()->addWarning($message);
    }
  }

}
